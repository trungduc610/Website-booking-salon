<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Availability
{
    public function plan(Branch $branch, array $data, ?int $excludeBookingId = null): array
    {
        $this->assertBranch($branch, $data);
        $zone = $branch->timezone;
        $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['time'], $zone);
        $policy = DB::table('branch_booking_policies')->where('branch_id', $branch->id)->first();
        $this->assertWindow($start, $policy);
        $ids = array_values(array_unique(array_map('intval', $data['service_ids'])));
        $services = $this->services($branch, $ids);
        $end = $this->end($start, $services);
        $hours = DB::table('branch_working_hours')->where('branch_id', $branch->id)->where('day_of_week', $start->dayOfWeek)->first();
        $holiday = DB::table('branch_holidays')->where('branch_id', $branch->id)->where('date', $data['date'])->where('is_closed', true)->exists();
        $this->assertHours($start, $end, $hours, $holiday);
        $staff = $this->staff($branch, $data, $ids)
            ->whereHas('hours', fn ($q) => $q->where('day_of_week', $start->dayOfWeek)->where('is_off', false)
                ->where('start_time', '<=', $start->format('H:i'))->where('end_time', '>=', $end->format('H:i')))
            ->whereDoesntHave('leaves', fn ($q) => $q->where('status', 'APPROVED')
                ->where('start_at', '<', $end->format('Y-m-d H:i:s'))->where('end_at', '>', $start->format('Y-m-d H:i:s')))
            ->get();
        $busy = $this->busy($branch, $staff->modelKeys(), $start, $excludeBookingId);
        $staff = $this->freeStaff($staff, $busy, $start, $end, $policy->default_buffer_minutes ?? 0);
        $cents = $this->cents($services);
        return compact('start', 'end', 'staff', 'services', 'cents');
    }

    public function slots(Branch $branch, array $data): array
    {
        $day = CarbonImmutable::parse($data['date'], $branch->timezone);
        $hours = DB::table('branch_working_hours')->where('branch_id', $branch->id)->where('day_of_week', $day->dayOfWeek)->first();
        $holiday = $hours && ! $hours->is_closed
            && DB::table('branch_holidays')->where('branch_id', $branch->id)->where('date', $data['date'])->where('is_closed', true)->exists();
        if (! $hours || $hours->is_closed || $holiday) {
            return ['slots' => [], 'message' => 'Salon không mở cửa vào ngày này.'];
        }
        $cursor = CarbonImmutable::parse($data['date'].' '.$hours->open_time, $branch->timezone);
        $close = CarbonImmutable::parse($data['date'].' '.$hours->close_time, $branch->timezone);
        $commonError = null;
        try {
            $this->assertBranch($branch, $data);
        } catch (ValidationException $e) {
            $commonError = $e;
        }
        $policy = $commonError ? null : DB::table('branch_booking_policies')->where('branch_id', $branch->id)->first();
        $now = CarbonImmutable::instance(now($branch->timezone));
        $ids = array_values(array_unique(array_map('intval', $data['service_ids'])));
        $services = null;
        $serviceError = null;
        $slots = [];
        $intervals = [];
        // Everything reused below is local to this call; plan() always reads fresh data.
        for ($i = 0; $cursor->lt($close) && $i < 48; $i++, $cursor = $cursor->addMinutes(30)) {
            $time = $cursor->format('H:i');
            try {
                if ($commonError) {
                    throw $commonError;
                }
                $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$time, $branch->timezone);
                $this->assertWindow($start, $policy, $now);
                // Defer service errors until after each choice's lead-time/horizon check.
                if ($services === null && $serviceError === null) {
                    try {
                        $services = $this->services($branch, $ids);
                    } catch (ValidationException $e) {
                        $serviceError = $e;
                    }
                }
                if ($serviceError) {
                    throw $serviceError;
                }
                $end = $this->end($start, $services);
                $this->assertHours($start, $end, $hours, false);
                $this->cents($services);
                $intervals[$i] = compact('start', 'end');
                $slots[$i] = ['time' => $time, 'available' => false, 'message' => 'Không còn chuyên viên phù hợp'];
            } catch (ValidationException $e) {
                $slots[$i] = ['time' => $time, 'available' => false, 'message' => collect($e->errors())->flatten()->first()];
            }
        }
        if ($intervals) {
            $staff = $this->staff($branch, $data, $ids)->get();
            if ($staff->isNotEmpty()) {
                // Evaluate all time choices in one SQL query, retaining the database's TIME comparison semantics.
                $hourQuery = DB::table('staff_working_hours')->whereIn('staff_id', $staff->modelKeys())
                    ->where('day_of_week', $day->dayOfWeek)->where('is_off', false)->select('staff_id');
                foreach ($intervals as $index => $interval) {
                    $hourQuery->selectRaw('CASE WHEN start_time <= ? AND end_time >= ? THEN 1 ELSE 0 END AS slot_'.$index, [
                        $interval['start']->format('H:i'), $interval['end']->format('H:i'),
                    ]);
                }
                $staffHours = $hourQuery->get()->groupBy('staff_id');
                $leaves = DB::table('staff_leaves')->whereIn('staff_id', $staff->modelKeys())->where('status', 'APPROVED')
                    ->where('start_at', '<', $day->addDay()->startOfDay()->format('Y-m-d H:i:s'))
                    ->where('end_at', '>', $day->startOfDay()->format('Y-m-d H:i:s'))->get()
                    ->map(fn ($row) => [
                        'staff_id' => $row->staff_id,
                        'start' => CarbonImmutable::parse($row->start_at, $branch->timezone),
                        'end' => CarbonImmutable::parse($row->end_at, $branch->timezone),
                    ])->groupBy('staff_id');
                $busy = $this->busy($branch, $staff->modelKeys(), $day);
                foreach ($intervals as $index => $interval) {
                    ['start' => $start, 'end' => $end] = $interval;
                    $eligible = $staff->filter(fn ($candidate) => $staffHours->get($candidate->id, collect())
                        ->contains(fn ($row) => (bool) $row->{'slot_'.$index})
                        && ! $leaves->get($candidate->id, collect())->contains(fn ($leave) => $leave['start']->lt($end) && $leave['end']->gt($start)));
                    $available = $this->freeStaff($eligible, $busy, $start, $end, $policy->default_buffer_minutes ?? 0)->isNotEmpty();
                    $slots[$index]['available'] = $available;
                    $slots[$index]['message'] = $available ? 'Còn chỗ' : 'Không còn chuyên viên phù hợp';
                }
            }
        }
        return ['slots' => array_values($slots), 'message' => 'Khung giờ được cập nhật khi kiểm tra; chưa giữ chỗ.'];
    }

    private function assertBranch(Branch $branch, array $data): void
    {
        $branch->loadMissing('business');
        if (! $branch->business || $branch->business->status !== 'ACTIVE' || $branch->operational_status !== 'ACTIVE') {
            $this->fail('Chi nhánh hiện không nhận đặt lịch.');
        }
        if ($branch->staff_assignment_mode === 'MANUAL_ASSIGN_BY_RECEPTIONIST') {
            $this->fail('Chi nhánh này chưa nhận đặt lịch trực tuyến. Vui lòng liên hệ salon.');
        }
        if ($branch->staff_assignment_mode === 'CUSTOMER_SELECTS_STAFF' && empty($data['staff_id'])) {
            $this->fail('Vui lòng chọn nhân viên cho lịch hẹn.');
        }
    }

    private function assertWindow(CarbonImmutable $start, $policy, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::instance(now($start->getTimezone()));
        if ($start->lt($now->addMinutes($policy->lead_time_minutes ?? 60)) || $start->gt($now->addDays($policy->booking_horizon_days ?? 90))) {
            $this->fail('Ngày giờ nằm ngoài thời gian nhận đặt lịch.');
        }
    }

    private function services(Branch $branch, array $ids): Collection
    {
        $services = $branch->services()->where('business_id', $branch->business_id)->whereIn('id', $ids)
            ->where('status', 'ACTIVE')->where('bookable', true)->get()->keyBy('id');
        if ($services->count() !== count($ids)) {
            $this->fail('Có dịch vụ không còn nhận đặt lịch.');
        }
        return collect($ids)->map(fn ($id) => $services->get($id));
    }

    private function end(CarbonImmutable $start, Collection $services): CarbonImmutable
    {
        $duration = (int) $services->sum('duration_minutes');
        if ($duration < 1) {
            $this->fail('Thời lượng dịch vụ không hợp lệ.');
        }
        $end = $start->addMinutes($duration);
        if (! $end->isSameDay($start)) {
            $this->fail('Lịch hẹn không được kéo dài qua nửa đêm.');
        }
        return $end;
    }

    private function assertHours(CarbonImmutable $start, CarbonImmutable $end, $hours, bool $holiday): void
    {
        if (! $hours || $hours->is_closed || $holiday || $start->format('H:i') < substr($hours->open_time, 0, 5) || $end->format('H:i') > substr($hours->close_time, 0, 5)) {
            $this->fail('Khung giờ nằm ngoài giờ mở cửa.');
        }
    }

    private function staff(Branch $branch, array $data, array $ids)
    {
        return $branch->staff()->where('status', 'ACTIVE')->where('is_bookable', true)
            ->when($data['staff_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->whereHas('services', fn ($q) => $q->whereIn('services.id', $ids), '=', count($ids))
            ->orderBy('id');
    }

    private function busy(Branch $branch, array $staffIds, CarbonImmutable $day, ?int $excludeBookingId = null): Collection
    {
        // Include adjacent days for buffers crossing midnight, and parse each reservation once.
        return DB::table('booking_services as items')->join('bookings as b', 'b.id', '=', 'items.booking_id')
            ->where('b.branch_id', $branch->id)->whereNull('b.deleted_at')->whereIn('b.status', Booking::ACTIVE)
            ->when($excludeBookingId, fn ($q) => $q->where('b.id', '<>', $excludeBookingId))
            ->where(fn ($q) => $q->where('b.status', '<>', 'PENDING')->orWhereNull('b.pending_expires_at')->orWhere('b.pending_expires_at', '>', now()))
            ->whereNotIn('items.status', ['CANCELLED', 'SKIPPED'])->whereIn('items.staff_id', $staffIds)
            ->whereBetween('b.appointment_date', [$day->subDay()->toDateString(), $day->addDay()->toDateString()])
            ->get(['items.staff_id', 'b.appointment_date', 'b.appointment_start_time', 'b.appointment_end_time'])
            ->map(fn ($row) => [
                'staff_id' => $row->staff_id,
                'start' => CarbonImmutable::parse($row->appointment_date.' '.$row->appointment_start_time, $branch->timezone),
                'end' => CarbonImmutable::parse($row->appointment_date.' '.$row->appointment_end_time, $branch->timezone),
            ])->groupBy('staff_id');
    }

    private function freeStaff(Collection $staff, Collection $busy, CarbonImmutable $start, CarbonImmutable $end, int $buffer): Collection
    {
        return $staff->reject(fn ($candidate) => $busy->get($candidate->id, collect())->contains(
            fn ($row) => $start->lt($row['end']->addMinutes($buffer)) && $end->addMinutes($buffer)->gt($row['start'])
        ))->values();
    }

    private function cents(Collection $services): int
    {
        $cents = $services->sum(fn ($service) => (int) str_replace('.', '', $service->price));
        if ($cents > 999999999999) {
            $this->fail('Tổng giá trị vượt giới hạn cho phép.');
        }
        return $cents;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['time' => $message]);
    }
}
