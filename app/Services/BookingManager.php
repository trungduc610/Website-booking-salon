<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingManager
{
    public function __construct(private Availability $availability)
    {
    }

    public function create(User $user, Branch $branch, array $data): Booking
    {
        $data = $this->normalizeRequest($data);
        $payloadHash = hash('sha256', json_encode([
            'date' => $data['date'], 'time' => $data['time'], 'service_ids' => $data['service_ids'],
            'staff_id' => $data['staff_id'], 'voucher_code' => $data['voucher_code'], 'note' => $data['note'],
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $branch, $data, $payloadHash): Booking {
            $branch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $profile = $user->customerProfile()->firstOrFail();
            $existing = Booking::where('request_token', $data['request_token'])->first();
            if ($existing) {
                abort_unless($existing->customer_id === $profile->id && $existing->branch_id === $branch->id, 409, 'Mã yêu cầu đã được sử dụng cho khách hàng hoặc chi nhánh khác.');
                // Older bookings cannot be verified from mutable appointment/item snapshots.
                abort_if($existing->request_payload_hash === null, 409, 'Không thể xác minh dữ liệu ban đầu của mã yêu cầu này. Vui lòng kiểm tra lịch đã đặt trước khi gửi yêu cầu mới.');
                abort_unless(hash_equals($existing->request_payload_hash, $payloadHash), 409, 'Mã yêu cầu đã được sử dụng với dữ liệu đặt lịch khác. Vui lòng dùng mã yêu cầu mới.');
                return $existing;
            }
            $plan = $this->availability->plan($branch, $data);
            if ($plan['staff']->isEmpty()) {
                throw ValidationException::withMessages(['time' => 'Không còn nhân viên phù hợp trong khung giờ này.']);
            }
            $staff = $plan['staff']->first();
            $amount = sprintf('%d.%02d', intdiv($plan['cents'], 100), $plan['cents'] % 100);
            $status = $branch->booking_confirmation_mode === 'AUTO_CONFIRMATION' ? 'CONFIRMED' : 'PENDING';
            [$voucherId, $discount] = app(VoucherDiscount::class)->reserve($data['voucher_code'] ?? null, $branch, $profile->id, $plan['cents']);
            $booking = Booking::create([
                'customer_id' => $profile->id, 'branch_id' => $branch->id,
                'booking_code' => 'GLW-'.strtoupper(Str::random(20)), 'request_token' => $data['request_token'],
                'request_payload_hash' => $payloadHash,
                'appointment_date' => $data['date'], 'appointment_start_time' => $plan['start']->format('H:i:s'),
                'appointment_end_time' => $plan['end']->format('H:i:s'), 'status' => $status,
                'total_amount' => $amount, 'voucher_id' => $voucherId,
                'voucher_discount_amount' => VoucherDiscount::decimal($discount),
                'final_amount' => VoucherDiscount::decimal($plan['cents'] - $discount), 'note' => $data['note'] ?? null,
                'pending_expires_at' => $status === 'PENDING' ? now()->addMinutes(max(1, $branch->pending_hold_minutes)) : null,
            ]);
            $cursor = $plan['start'];
            foreach ($plan['services'] as $order => $service) {
                $end = $cursor->addMinutes($service->duration_minutes);
                $booking->items()->create([
                    'service_id' => $service->id, 'staff_id' => $staff->id, 'service_name_snapshot' => $service->name,
                    'price_at_booking' => $service->price, 'duration_minutes' => $service->duration_minutes,
                    'sort_order' => $order, 'status' => 'SCHEDULED',
                    'item_start_at' => $cursor->format('Y-m-d H:i:s'), 'item_end_at' => $end->format('Y-m-d H:i:s'),
                ]);
                $cursor = $end;
            }
            DB::table('booking_status_histories')->insert(['booking_id' => $booking->id, 'status' => $status, 'changed_by' => $user->id]);
            return $booking;
        }, 3);
    }

    private function normalizeRequest(array $data): array
    {
        // Service order determines the item schedule; keep it while normalizing IDs.
        $data['service_ids'] = array_values(array_map('intval', $data['service_ids']));
        $data['staff_id'] = isset($data['staff_id']) && $data['staff_id'] !== '' ? (int) $data['staff_id'] : null;
        $code = strtoupper(trim($data['voucher_code'] ?? ''));
        $data['voucher_code'] = $code === '' ? null : $code;
        $note = trim(str_replace(["\r\n", "\r"], "\n", $data['note'] ?? ''));
        $data['note'] = $note === '' ? null : $note;

        return $data;
    }

    public function transition(User $user, Booking $booking, string $status, bool $customer = false): void
    {
        DB::transaction(function () use ($user, $booking, $status, $customer): void {
            $branch = Branch::whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($customer) {
                abort_unless($booking->customer_id === $user->customerProfile()->value('id'), 403);
            } else {
                abort_unless($user->can('manageBookings', $branch), 403);
            }
            $map = ['PENDING' => ['CONFIRMED', 'REJECTED', 'CANCELLED'], 'CONFIRMED' => ['CHECKED_IN', 'CANCELLED', 'NO_SHOW'],
                'CHECKED_IN' => ['IN_PROGRESS', 'CANCELLED', 'NO_SHOW'], 'IN_PROGRESS' => ['COMPLETED', 'CANCELLED']];
            $error = !in_array($status, $map[$booking->status] ?? [], true);
            $expired = $booking->status === 'PENDING' && $booking->pending_expires_at && now()->gte($booking->pending_expires_at);
            if ($customer) {
                $cancelHours = DB::table('branch_booking_policies')->where('branch_id', $branch->id)->value('cancellation_hours') ?? 24;
                $start = \Carbon\CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_start_time, $branch->timezone);
                $error = $error || $status !== 'CANCELLED' || !in_array($booking->status, ['PENDING', 'CONFIRMED'], true) || now($branch->timezone)->gt($start->subHours($cancelHours));
            }
            if ($status === 'NO_SHOW') {
                $error = $error || now($branch->timezone)->lt(\Carbon\CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_start_time, $branch->timezone));
            }
            if ($error || $expired) {
                throw ValidationException::withMessages(['status' => 'Không thể chuyển trạng thái này hoặc lịch hẹn đã hết thời hạn.']);
            }
            $booking->status = $status;
            if (in_array($status, ['CANCELLED', 'REJECTED'], true)) {
                app(VoucherDiscount::class)->release($booking);
                $booking->cancelled_at = now();
                $booking->cancelled_by = $user->id;
            }
            $booking->save();
            if (in_array($status, ['CANCELLED', 'REJECTED', 'NO_SHOW'], true)) {
                $booking->items()->update(['status' => 'CANCELLED']);
            } elseif ($status === 'COMPLETED') {
                $booking->items()->update(['status' => 'COMPLETED']);
            }
            DB::table('booking_status_histories')->insert(['booking_id' => $booking->id, 'status' => $status, 'changed_by' => $user->id]);
        }, 3);
    }
}
