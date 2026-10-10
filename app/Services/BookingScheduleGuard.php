<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\StaffProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingScheduleGuard
{
    // Call under the same branch lock used by booking creation and rescheduling.
    private function bookings(Branch $branch, ?int $staffId = null): Collection
    {
        $now = now($branch->timezone);
        $remainingItems = fn ($query) => $query->whereNotIn('status', ['CANCELLED', 'SKIPPED', 'COMPLETED'])
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->where(fn ($query) => $query->whereNull('item_end_at')->orWhere('item_end_at', '>', $now->format('Y-m-d H:i:s')));

        return Booking::where('branch_id', $branch->id)->whereIn('status', Booking::ACTIVE)
            ->where(fn ($query) => $query->where('status', '<>', 'PENDING')
                ->orWhereNull('pending_expires_at')->orWhere('pending_expires_at', '>', now()))
            ->where(fn ($query) => $query->where('appointment_date', '>', $now->toDateString())
                ->orWhere(fn ($query) => $query->where('appointment_date', $now->toDateString())
                    ->where('appointment_end_time', '>', $now->format('H:i:s'))))
            ->whereHas('items', $remainingItems)->with(['items' => $remainingItems])
            ->orderBy('appointment_date')->orderBy('appointment_start_time')->get();
    }

    public function staffProfile(Branch $branch, StaffProfile $staff, array $data): void
    {
        $disabled = ($staff->status === 'ACTIVE' && $data['status'] !== 'ACTIVE')
            || ($staff->is_bookable && ! $data['is_bookable']);
        $removed = array_diff($staff->services()->pluck('services.id')->all(), array_map('intval', $data['service_ids']));
        if (! $disabled && ! $removed) {
            return;
        }
        $conflicts = $this->bookings($branch, $staff->id)->filter(fn ($booking) => $disabled
            || $booking->items->contains(fn ($item) => in_array($item->service_id, $removed)));
        $this->reject($branch, $conflicts, 'schedule');
    }

    public function staffHours(Branch $branch, StaffProfile $staff, array $hours): void
    {
        $old = $staff->hours()->get()->keyBy('day_of_week');
        $new = collect($hours)->keyBy('day_of_week');
        $conflicts = $this->bookings($branch, $staff->id)->filter(function ($booking) use ($branch, $old, $new): bool {
            return $booking->items->contains(function ($item) use ($booking, $branch, $old, $new): bool {
                [$start, $end] = $this->itemInterval($branch, $booking, $item);
                return $this->covers($old->get($start->dayOfWeek), $start, $end)
                    && ! $this->covers($new->get($start->dayOfWeek), $start, $end);
            });
        });
        $this->reject($branch, $conflicts, 'schedule');
    }

    public function leave(Branch $branch, StaffProfile $staff, array $data): void
    {
        $leaveStart = CarbonImmutable::parse($data['start_at'], $branch->timezone);
        $leaveEnd = CarbonImmutable::parse($data['end_at'], $branch->timezone);
        $conflicts = $this->bookings($branch, $staff->id)->filter(function ($booking) use ($branch, $leaveStart, $leaveEnd): bool {
            return $booking->items->contains(function ($item) use ($booking, $branch, $leaveStart, $leaveEnd): bool {
                [$start, $end] = $this->itemInterval($branch, $booking, $item);
                $start = $start->max(now($branch->timezone));
                return $leaveStart->lt($end) && $leaveEnd->gt($start);
            });
        });
        $this->reject($branch, $conflicts, 'schedule');
    }

    public function branchHours(Branch $branch, array $hours, int $buffer): void
    {
        $old = DB::table('branch_working_hours')->where('branch_id', $branch->id)->get()->keyBy('day_of_week')
            ->map(fn ($row) => ['start_time' => $row->open_time, 'end_time' => $row->close_time, 'is_off' => $row->is_closed]);
        $new = collect($hours)->keyBy('day_of_week');
        $bookings = $this->bookings($branch);
        $conflicts = $bookings->filter(function ($booking) use ($branch, $old, $new): bool {
            [$start, $end] = $this->bookingInterval($branch, $booking);
            return $this->covers($old->get($start->dayOfWeek), $start, $end)
                && ! $this->covers($new->get($start->dayOfWeek), $start, $end);
        });
        $oldBuffer = (int) DB::table('branch_booking_policies')->where('branch_id', $branch->id)->value('default_buffer_minutes');
        if ($buffer > $oldBuffer) {
            // Match Availability's buffer between whole appointments for each assigned worker.
            $byStaff = collect();
            foreach ($bookings as $booking) {
                foreach ($booking->items->pluck('staff_id')->filter()->unique() as $staffId) {
                    $byStaff->push(['staff_id' => $staffId, 'booking' => $booking]);
                }
            }
            foreach ($byStaff->groupBy('staff_id') as $rows) {
                $rows = $rows->values();
                foreach ($rows as $index => $left) {
                    [, $end] = $this->bookingInterval($branch, $left['booking']);
                    foreach ($rows->slice($index + 1) as $right) {
                        [$start] = $this->bookingInterval($branch, $right['booking']);
                        if ($start->gte($end->addMinutes($buffer))) {
                            break;
                        }
                        if ($start->gte($end->addMinutes($oldBuffer))) {
                            $conflicts->push($left['booking'], $right['booking']);
                        }
                    }
                }
            }
        }
        $this->reject($branch, $conflicts->unique('id'), 'hours');
    }

    public function holiday(Branch $branch, string $date): void
    {
        $this->reject($branch, $this->bookings($branch)->where('appointment_date', $date), 'hours');
    }

    private function covers($row, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $row && ! data_get($row, 'is_off') && $start->isSameDay($end)
            && str_pad(data_get($row, 'start_time'), 8, ':00') <= $start->format('H:i:s')
            && str_pad(data_get($row, 'end_time'), 8, ':00') >= $end->format('H:i:s');
    }

    private function bookingInterval(Branch $branch, Booking $booking): array
    {
        return [
            CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_start_time, $branch->timezone),
            CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_end_time, $branch->timezone),
        ];
    }

    private function itemInterval(Branch $branch, Booking $booking, $item): array
    {
        [$start, $end] = $this->bookingInterval($branch, $booking);
        return [
            $item->item_start_at ? CarbonImmutable::parse($item->item_start_at, $branch->timezone) : $start,
            $item->item_end_at ? CarbonImmutable::parse($item->item_end_at, $branch->timezone) : $end,
        ];
    }

    private function reject(Branch $branch, Collection $bookings, string $key): void
    {
        if ($bookings->isEmpty()) {
            return;
        }
        $conflicts = $bookings->map(fn ($booking) => [
            'id' => $booking->id, 'branch_id' => $branch->id, 'code' => $booking->booking_code,
            'date' => $booking->appointment_date, 'start' => substr($booking->appointment_start_time, 0, 5),
            'end' => substr($booking->appointment_end_time, 0, 5),
        ])->values()->all();
        if (request()->hasSession()) {
            request()->session()->flash('schedule_conflicts', $conflicts);
        }
        throw ValidationException::withMessages([$key => [
            'Thay đổi này làm lịch hẹn mất điều kiện phục vụ. Hãy xử lý các lịch xung đột hoặc dời lịch khi còn được phép trước khi lưu lại.',
            ...array_map(fn ($row) => $row['code'].' · '.$row['date'].' '.$row['start'].'–'.$row['end'], $conflicts),
        ]]);
    }
}
