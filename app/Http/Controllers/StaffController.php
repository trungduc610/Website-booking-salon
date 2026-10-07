<?php

namespace App\Http\Controllers;

use App\Http\Requests\HoursRequest;
use App\Http\Requests\StaffRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index(Branch $branch)
    {
        Gate::authorize('view', $branch);
        return view('staff.index', ['branch' => $branch, 'staffList' => $branch->staff()->with('services')->orderBy('full_name')->paginate(15)]);
    }

    public function create(Branch $branch)
    {
        Gate::authorize('update', $branch);
        return $this->form($branch, new StaffProfile(['status' => 'ACTIVE', 'is_bookable' => false]));
    }

    public function store(StaffRequest $request, Branch $branch)
    {
        DB::transaction(function () use ($request, $branch): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $staff = $branch->staff()->create($request->safe()->except('service_ids'));
            $staff->services()->sync($request->validated('service_ids'));
        });
        return redirect()->route('staff.index', $branch)->with('success', 'Đã thêm nhân viên. Hãy thiết lập lịch làm trước khi nhận đặt lịch.');
    }

    public function edit(Branch $branch, StaffProfile $staff)
    {
        Gate::authorize('update', $branch);
        return $this->form($branch, $staff);
    }

    public function update(StaffRequest $request, Branch $branch, StaffProfile $staff)
    {
        DB::transaction(function () use ($request, $branch, $staff): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $this->assertNoFutureBookings($branch, $staff);
            $staff->update($request->safe()->except('service_ids'));
            $staff->services()->sync($request->validated('service_ids'));
        });
        return back()->with('success', 'Đã cập nhật nhân viên.');
    }

    public function hours(HoursRequest $request, Branch $branch, StaffProfile $staff)
    {
        DB::transaction(function () use ($request, $branch, $staff): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $this->assertNoFutureBookings($branch, $staff);
            foreach ($request->validated('hours') as $row) {
                $staff->hours()->updateOrCreate(['day_of_week' => $row['day_of_week']], $row);
            }
        });
        return back()->with('success', 'Đã cập nhật lịch làm.');
    }

    public function leave(Request $request, Branch $branch, StaffProfile $staff)
    {
        Gate::authorize('update', $branch);
        $data = $request->validate([
            'start_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_at'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $data['start_at'] = \Carbon\CarbonImmutable::parse($data['start_at'], $branch->timezone)->format('Y-m-d H:i:s');
        $data['end_at'] = \Carbon\CarbonImmutable::parse($data['end_at'], $branch->timezone)->format('Y-m-d H:i:s');
        DB::transaction(function () use ($data, $request, $branch, $staff): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $this->assertNoFutureBookings($branch, $staff);
            $staff->leaves()->create([...$data, 'status' => 'APPROVED', 'reviewed_by' => $request->user()->id]);
        });
        return back()->with('success', 'Đã ghi nhận nghỉ phép.');
    }

    public function cancelLeave(Request $request, Branch $branch, StaffProfile $staff, int $leave)
    {
        Gate::authorize('update', $branch);
        DB::transaction(function () use ($request, $branch, $staff, $leave): void {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $staff->leaves()->findOrFail($leave)->update(['status' => 'CANCELLED', 'reviewed_by' => $request->user()->id]);
        });
        return back()->with('success', 'Đã hủy khoảng nghỉ.');
    }

    private function form(Branch $branch, StaffProfile $staff)
    {
        $staff->load(['services', 'hours']);
        $leaves = $staff->exists ? $staff->leaves()->orderByDesc('start_at')->paginate(10) : collect();
        return view('staff.form', ['branch' => $branch, 'staff' => $staff, 'services' => $branch->services()->orderBy('name')->get(), 'leaves' => $leaves]);
    }

    private function assertNoFutureBookings(Branch $branch, StaffProfile $staff): void
    {
        $hasBookings = DB::table('booking_services')->join('bookings', 'bookings.id', '=', 'booking_services.booking_id')
            ->where('booking_services.staff_id', $staff->id)->where('bookings.branch_id', $branch->id)
            ->whereIn('bookings.status', Booking::ACTIVE)->whereNull('bookings.deleted_at')
            ->where('bookings.appointment_date', '>=', now($branch->timezone)->toDateString())->exists();
        if ($hasBookings) {
            throw ValidationException::withMessages(['schedule' => 'Nhân viên có lịch hẹn chưa hoàn tất. Hãy xử lý lịch hẹn trước khi đổi hồ sơ, ca làm hoặc thêm ngày nghỉ.']);
        }
    }
}
