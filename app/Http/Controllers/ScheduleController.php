<?php

namespace App\Http\Controllers;

use App\Http\Requests\HoursRequest;
use App\Models\Branch;
use App\Services\BookingScheduleGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function __construct(private BookingScheduleGuard $scheduleGuard)
    {
    }

    public function edit(Branch $branch)
    {
        Gate::authorize('update', $branch);
        return view('staff.schedule', [
            'branch' => $branch,
            'hours' => DB::table('branch_working_hours')->where('branch_id', $branch->id)->get(),
            'policy' => DB::table('branch_booking_policies')->where('branch_id', $branch->id)->first(),
            'holidays' => DB::table('branch_holidays')->where('branch_id', $branch->id)->orderByDesc('date')->paginate(12),
        ]);
    }

    public function update(HoursRequest $request, Branch $branch)
    {
        $policy = $request->validate([
            'lead_time_minutes' => ['required', 'integer', 'between:0,10080'],
            'booking_horizon_days' => ['required', 'integer', 'between:1,365'],
            'default_buffer_minutes' => ['required', 'integer', 'between:0,120'],
            'cancellation_hours' => ['required', 'integer', 'between:0,168'],
            'reschedule_hours' => ['required', 'integer', 'between:0,168'],
        ]);
        DB::transaction(function () use ($request, $branch, $policy): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $this->scheduleGuard->branchHours($branch, $request->validated('hours'), (int) $policy['default_buffer_minutes']);
            foreach ($request->validated('hours') as $row) {
                DB::table('branch_working_hours')->updateOrInsert(
                    ['branch_id' => $branch->id, 'day_of_week' => $row['day_of_week']],
                    ['open_time' => $row['start_time'], 'close_time' => $row['end_time'], 'is_closed' => $row['is_off']]
                );
            }
            DB::table('branch_booking_policies')->updateOrInsert(['branch_id' => $branch->id], [...$policy, 'updated_at' => now()]);
        });
        return back()->with('success', 'Đã lưu giờ mở cửa và chính sách đặt lịch.');
    }

    public function holiday(Request $request, Branch $branch)
    {
        Gate::authorize('update', $branch);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'name' => ['required', 'string', 'max:200']]);
        DB::transaction(function () use ($branch, $data): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $this->scheduleGuard->holiday($branch, $data['date']);
            DB::table('branch_holidays')->updateOrInsert(['branch_id' => $branch->id, 'date' => $data['date']], ['name' => $data['name'], 'is_closed' => true]);
        });
        return back()->with('success', 'Đã lưu ngày đóng cửa.');
    }

    public function reopen(Branch $branch, int $holiday)
    {
        Gate::authorize('update', $branch);
        DB::transaction(function () use ($branch, $holiday): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $row = DB::table('branch_holidays')->where('branch_id', $branch->id)->where('id', $holiday)->first();
            abort_unless($row, 404);
            DB::table('branch_holidays')->where('id', $row->id)->update(['is_closed' => false]);
        });
        return back()->with('success', 'Đã mở lại ngày này theo giờ làm thông thường.');
    }
}
