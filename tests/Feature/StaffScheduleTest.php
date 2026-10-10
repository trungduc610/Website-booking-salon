<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class StaffScheduleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function hoursPayload(): array
    {
        return ['hours' => array_map(fn ($day) => ['day_of_week' => $day,'start_time' => '08:00','end_time' => '18:00','is_off' => 0], range(0, 6))];
    }

    public function test_staff_pages_and_weekly_hours_work(): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user']);
        $this->get(route('staff.index', $f['branch']))->assertOk();
        $this->get(route('staff.create', $f['branch']))->assertOk();
        $this->get(route('staff.edit', [$f['branch'],$f['staff']]))->assertOk();
        $this->put(route('staff.hours', [$f['branch'],$f['staff']]), $this->hoursPayload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('staff_working_hours', 7);
        $this->get(route('schedule.edit', $f['branch']))->assertOk();
    }

    public function test_hours_require_seven_distinct_days_and_valid_time_order(): void
    {
        $f = $this->bookingFixture();
        $data = $this->hoursPayload();
        $data['hours'][0]['end_time'] = '07:00';
        $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'],$f['staff']]), $data)->assertSessionHasErrors('hours.0.end_time');
        $data = $this->hoursPayload();
        $data['hours'][1]['day_of_week'] = 0;
        $this->put(route('staff.hours', [$f['branch'],$f['staff']]), $data)->assertSessionHasErrors('hours.0.day_of_week');
    }

    public function test_staff_binding_cannot_escape_authorized_branch(): void
    {
        $f = $this->bookingFixture();
        $other = Branch::create(['business_id' => $f['business']->id,'name' => 'Other branch']);
        $foreign = $other->staff()->create(['full_name' => 'Foreign','status' => 'ACTIVE','is_bookable' => true]);
        $this->actingAs($f['user'])->get(route('staff.edit', [$f['branch'],$foreign]))->assertNotFound();
    }

    public function test_staff_role_cannot_write_schedules(): void
    {
        $f = $this->bookingFixture();
        $f['user']->roles()->detach();
        $f['user']->roles()->attach(Role::where('code', 'STAFF')->sole()->id, ['business_id' => $f['business']->id,'branch_id' => $f['branch']->id]);
        $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'],$f['staff']]), $this->hoursPayload())->assertForbidden();
    }

    public function test_branch_hours_and_holiday_can_be_saved_and_reopened(): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user']);
        $this->put(route('schedule.update', $f['branch']), [...$this->hoursPayload(),'lead_time_minutes' => 0,'booking_horizon_days' => 90,'default_buffer_minutes' => 15,'cancellation_hours' => 24])->assertSessionHasNoErrors();
        $this->post(route('schedule.holiday', $f['branch']), ['date' => now()->addWeek()->toDateString(),'name' => 'Holiday'])->assertSessionHasNoErrors();
        $id = \Illuminate\Support\Facades\DB::table('branch_holidays')->value('id');
        $this->patch(route('schedule.reopen', [$f['branch'],$id]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_holidays', ['id' => $id,'is_closed' => 0]);
    }

    public function test_leave_uses_canonical_datetime_and_can_be_cancelled(): void
    {
        $f = $this->bookingFixture();
        $date = now()->addDays(3)->toDateString();
        $this->actingAs($f['user'])->post(route('staff.leave', [$f['branch'],$f['staff']]), ['start_at' => $date.'T09:00','end_at' => $date.'T12:00','reason' => 'Leave'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff_leaves', ['start_at' => $date.' 09:00:00','status' => 'APPROVED']);
        $leave = $f['staff']->leaves()->sole();
        $this->patch(route('staff.leave.cancel', [$f['branch'],$f['staff'],$leave->id]))->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $leave->fresh()->status);
    }

    public function test_unchanged_schedule_is_allowed_with_future_booking(): void
    {
        $f = $this->bookingFixture();
        app(\App\Services\BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'],$f['staff']]), $this->hoursPayload())->assertSessionHasNoErrors();
    }
}
