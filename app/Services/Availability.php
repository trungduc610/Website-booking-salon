<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Availability
{
    public function plan(Branch $branch, array $data): array
    {
        $branch->loadMissing('business');
        if (! $branch->business || $branch->business->status !== 'ACTIVE' || $branch->operational_status !== 'ACTIVE') {
            $this->fail('Chi nhánh hiện không nhận đặt lịch.');
        }
        $zone = $branch->timezone;
        if ($branch->staff_assignment_mode === 'MANUAL_ASSIGN_BY_RECEPTIONIST') {
            $this->fail('Chi nhánh này chưa nhận đặt lịch trực tuyến. Vui lòng liên hệ salon.');
        }
        if ($branch->staff_assignment_mode === 'CUSTOMER_SELECTS_STAFF' && empty($data['staff_id'])) {
            $this->fail('Vui lòng chọn nhân viên cho lịch hẹn.');
        }
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['time'], $zone);
        $policy = DB::table('branch_booking_policies')->where('branch_id', $branch->id)->first();
        $lead = $policy->lead_time_minutes ?? 60;
        $horizon = $policy->booking_horizon_days ?? 90;
        $buffer = $policy->default_buffer_minutes ?? 0;
        if ($start->lessThan(now($zone)->addMinutes($lead)) || $start->greaterThan(now($zone)->addDays($horizon))) {
            $this->fail('Ngày giờ nằm ngoài thời gian nhận đặt lịch.');
        }
        $ids = array_values(array_unique(array_map('intval', $data['service_ids'])));
        $services = $branch->services()->where('business_id', $branch->business_id)->whereIn('id', $ids)
            ->where('status', 'ACTIVE')->where('bookable', true)->get()->keyBy('id');
        if ($services->count() !== count($ids)) {
            $this->fail('Có dịch vụ không còn nhận đặt lịch.');
        }
        $services = collect($ids)->map(fn ($id) => $services->get($id));
        $duration = (int) $services->sum('duration_minutes');
        if ($duration < 1) {
            $this->fail('Thời lượng dịch vụ không hợp lệ.');
        }
        $end = $start->addMinutes($duration);
        if ($end->toDateString() !== $start->toDateString()) {
            $this->fail('Lịch hẹn không được kéo dài qua nửa đêm.');
        }
        $hours = DB::table('branch_working_hours')->where('branch_id', $branch->id)->where('day_of_week', $start->dayOfWeek)->first();
        $holiday = DB::table('branch_holidays')->where('branch_id', $branch->id)->where('date', $data['date'])->where('is_closed', true)->exists();
        if (! $hours || $hours->is_closed || $holiday || $start->format('H:i') < substr($hours->open_time, 0, 5) || $end->format('H:i') > substr($hours->close_time, 0, 5)) {
            $this->fail('Khung giờ nằm ngoài giờ mở cửa.');
        }
        $staff = $branch->staff()->where('status', 'ACTIVE')->where('is_bookable', true)
            ->when($data['staff_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->whereHas('services', fn ($q) => $q->whereIn('services.id', $ids), '=', count($ids))
            ->whereHas('hours', fn ($q) => $q->where('day_of_week', $start->dayOfWeek)->where('is_off', false)
                ->where('start_time', '<=', $start->format('H:i'))->where('end_time', '>=', $end->format('H:i')))
            ->whereDoesntHave('leaves', fn ($q) => $q->where('status', 'APPROVED')
                ->where('start_at', '<', $end->format('Y-m-d H:i:s'))->where('end_at', '>', $start->format('Y-m-d H:i:s')))
            ->orderBy('id')->get();
        // Load reservations once for all candidates, including adjacent days for buffers crossing midnight.
        $busy = DB::table('booking_services as items')->join('bookings as b', 'b.id', '=', 'items.booking_id')
            ->where('b.branch_id', $branch->id)->whereNull('b.deleted_at')->whereIn('b.status', Booking::ACTIVE)
            ->where(fn ($q) => $q->where('b.status', '<>', 'PENDING')->orWhereNull('b.pending_expires_at')->orWhere('b.pending_expires_at', '>', now()))
            ->whereNotIn('items.status', ['CANCELLED', 'SKIPPED'])->whereIn('items.staff_id', $staff->modelKeys())
            ->whereBetween('b.appointment_date', [$start->subDay()->toDateString(), $end->addDay()->toDateString()])
            ->get(['items.staff_id', 'b.appointment_date', 'b.appointment_start_time', 'b.appointment_end_time']);
        $staff = $staff->reject(function ($candidate) use ($busy, $start, $end, $buffer, $zone): bool {
            return $busy->contains(function ($row) use ($candidate, $start, $end, $buffer, $zone): bool {
                if ((int) $row->staff_id !== $candidate->id) {
                    return false;
                }
                $oldStart = CarbonImmutable::parse($row->appointment_date.' '.$row->appointment_start_time, $zone);
                $oldEnd = CarbonImmutable::parse($row->appointment_date.' '.$row->appointment_end_time, $zone);
                return $start->lessThan($oldEnd->addMinutes($buffer)) && $end->addMinutes($buffer)->greaterThan($oldStart);
            });
        })->values();
        $cents = $services->sum(fn ($service) => (int) str_replace('.', '', $service->price));
        if ($cents > 999999999999) {
            $this->fail('Tổng giá trị vượt giới hạn cho phép.');
        }
        return compact('start', 'end', 'staff', 'services', 'cents');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['time' => $message]);
    }
}
