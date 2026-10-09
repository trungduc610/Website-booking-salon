<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingRescheduler
{
    public function move(User $user, Booking $booking, array $data, bool $customer): void
    {
        DB::transaction(function () use ($user, $booking, $data, $customer): void {
            $branch = Branch::whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($customer) {
                abort_unless($booking->customer_id === $user->customerProfile()->value('id'), 403);
            } else {
                abort_unless($user->can('manageBookings', $branch), 403);
            }
            $items = $booking->items()->orderBy('sort_order')->get();
            $originalStart = $booking->appointment_date.' '.$booking->appointment_start_time;
            if (!in_array($booking->status, ['PENDING', 'CONFIRMED'], true)
                || ($booking->pending_expires_at && now()->gte($booking->pending_expires_at))
                || $originalStart !== $data['original_start']
                || $items->pluck('staff_id')->unique()->count() !== 1
                || (int) $items->first()?->staff_id !== (int) $data['original_staff_id']) {
                throw ValidationException::withMessages(['date' => 'Lịch đã thay đổi hoặc không thể đổi giờ. Vui lòng tải lại trang.']);
            }
            $start = CarbonImmutable::parse($originalStart, $branch->timezone);
            $hours = DB::table('branch_booking_policies')->where('branch_id', $branch->id)->value('reschedule_hours') ?? 12;
            if ($customer && now($branch->timezone)->gt($start->subHours($hours))) {
                throw ValidationException::withMessages(['date' => 'Đã quá thời hạn tự đổi lịch. Vui lòng liên hệ salon.']);
            }
            $serviceIds = $items->pluck('service_id')->all();
            $services = $branch->services()->whereIn('id', $serviceIds)->get()->keyBy('id');
            // Do not silently change a purchased service's duration, price, or voucher.
            foreach ($items as $item) {
                if (!$services->has($item->service_id) || $services[$item->service_id]->duration_minutes !== (int) $item->duration_minutes) {
                    throw ValidationException::withMessages(['date' => 'Dịch vụ đã thay đổi thời lượng. Vui lòng liên hệ salon để đổi lịch.']);
                }
            }
            $staffId = $customer ? $items->first()->staff_id : $data['staff_id'];
            $plan = app(Availability::class)->plan($branch, [
                'date' => $data['date'], 'time' => $data['time'], 'staff_id' => $staffId, 'service_ids' => $serviceIds,
            ], $booking->id);
            if ($plan['staff']->isEmpty()) {
                throw ValidationException::withMessages(['time' => 'Khung giờ mới không còn trống. Lịch hiện tại của bạn vẫn được giữ nguyên.']);
            }
            $cursor = $plan['start'];
            foreach ($items as $item) {
                $end = $cursor->addMinutes($item->duration_minutes);
                $item->update(['staff_id' => $staffId, 'item_start_at' => $cursor->format('Y-m-d H:i:s'), 'item_end_at' => $end->format('Y-m-d H:i:s')]);
                $cursor = $end;
            }
            $booking->update(['appointment_date' => $data['date'], 'appointment_start_time' => $plan['start']->format('H:i:s'), 'appointment_end_time' => $plan['end']->format('H:i:s')]);
            DB::table('booking_status_histories')->insert([
                'booking_id' => $booking->id, 'status' => $booking->status, 'changed_by' => $user->id,
                'note' => 'Rescheduled: '.$originalStart.' (staff '.$data['original_staff_id'].') -> '.$data['date'].' '.$data['time'].' (staff '.$staffId.').',
            ]);
        }, 3);
    }
}
