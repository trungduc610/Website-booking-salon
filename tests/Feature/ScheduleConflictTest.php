<?php

namespace Tests\Feature;

use App\Services\BookingManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function staffPayload(array $f, array $extra = []): array
    {
        return array_replace([
            'full_name' => $f['staff']->full_name, 'position' => 'Stylist', 'bio' => 'Original',
            'status' => 'ACTIVE', 'is_bookable' => 1, 'service_ids' => [$f['service']->id],
        ], $extra);
    }

    private function hoursPayload(array $f, array $extra = []): array
    {
        $day = CarbonImmutable::parse($this->bookingData($f)['date'])->dayOfWeek;
        return ['hours' => array_map(fn ($weekday) => array_replace([
            'day_of_week' => $weekday, 'start_time' => '08:00', 'end_time' => '18:00', 'is_off' => 0,
        ], $weekday === $day ? $extra : []), range(0, 6))];
    }

    private function branchPayload(array $f, array $hours = [], array $policy = []): array
    {
        return [...$this->hoursPayload($f, $hours), ...array_replace([
            'lead_time_minutes' => 0, 'booking_horizon_days' => 90,
            'default_buffer_minutes' => 0, 'cancellation_hours' => 0,
        ], $policy)];
    }

    public function test_description_and_unused_service_changes_do_not_block_existing_booking(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $unused = $f['service']->replicate();
        $unused->name = 'Unused';
        $unused->save();
        $f['staff']->services()->attach($unused->id);
        $this->actingAs($f['user'])->put(route('staff.update', [$f['branch'], $f['staff']]), $this->staffPayload($f, [
            'full_name' => 'Updated name', 'bio' => 'New description', 'position' => 'Senior stylist', 'employee_code' => 'NEW',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('New description', $f['staff']->fresh()->bio);
        $this->assertSame('Updated name', $f['staff']->fresh()->full_name);
        $this->assertSame([$f['service']->id], $f['staff']->services()->pluck('services.id')->all());
        $this->put(route('staff.update', [$f['branch'], $f['staff']]), $this->staffPayload($f, [
            'service_ids' => [$f['service']->id, $unused->id],
        ]))->assertSessionHasNoErrors();
        $this->assertSame($f['staff']->id, $booking->items()->sole()->staff_id);
        $this->assertDatabaseCount('booking_status_histories', 1);
    }

    public static function destructiveProfileChanges(): array
    {
        return [
            'remove booked skill' => [['service_ids' => []]],
            'stop booking' => [['is_bookable' => 0]],
            'deactivate' => [['status' => 'INACTIVE']],
            'on leave' => [['status' => 'ON_LEAVE']],
            'lock staff' => [['status' => 'LOCKED']],
        ];
    }

    #[DataProvider('destructiveProfileChanges')]
    public function test_changes_that_remove_booked_staff_conditions_are_rejected_atomically(array $changes): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->actingAs($f['user'])->putJson(route('staff.update', [$f['branch'], $f['staff']]), $this->staffPayload($f, [
            ...$changes, 'bio' => 'Must not save',
        ]))->assertUnprocessable()->assertJsonValidationErrors('schedule')->assertSee($booking->booking_code);
        $this->assertNull($f['staff']->fresh()->bio);
        $this->assertTrue($f['staff']->fresh()->is_bookable);
        $this->assertSame('ACTIVE', $f['staff']->fresh()->status);
        $this->assertSame([$f['service']->id], $f['staff']->services()->pluck('services.id')->all());
    }

    public static function weeklyHoursChanges(): array
    {
        return [
            'unchanged' => [[], false],
            'start at appointment' => [['start_time' => '10:00'], false],
            'end at appointment end' => [['end_time' => '11:00'], false],
            'extend coverage' => [['start_time' => '07:00', 'end_time' => '19:00'], false],
            'late start' => [['start_time' => '10:30'], true],
            'early end' => [['end_time' => '10:30'], true],
            'day off' => [['is_off' => 1], true],
        ];
    }

    #[DataProvider('weeklyHoursChanges')]
    public function test_staff_hours_only_block_lost_coverage(array $change, bool $conflict): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $old = $f['staff']->hours()->get()->toArray();
        $response = $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'], $f['staff']]), $this->hoursPayload($f, $change));
        if ($conflict) {
            $response->assertSessionHasErrors('schedule')->assertSessionHas('schedule_conflicts', fn ($rows) => array_column($rows, 'id') === [$booking->id]);
            $this->assertSame($old, $f['staff']->hours()->get()->toArray());
        } else {
            $response->assertSessionHasNoErrors();
            $day = CarbonImmutable::parse($booking->appointment_date)->dayOfWeek;
            $saved = $f['staff']->hours()->where('day_of_week', $day)->sole();
            $this->assertSame($change['start_time'] ?? '08:00', substr($saved->start_time, 0, 5));
            $this->assertSame($change['end_time'] ?? '18:00', substr($saved->end_time, 0, 5));
            $this->assertFalse((bool) $saved->is_off);
        }
        $this->assertDatabaseCount('booking_status_histories', 1);
    }

    #[DataProvider('weeklyHoursChanges')]
    public function test_branch_hours_only_block_lost_coverage_and_keep_policy_atomic(array $change, bool $conflict): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $oldHours = DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->get()->toArray();
        $oldPolicy = DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->first();
        $response = $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->branchPayload($f, $change, [
            'lead_time_minutes' => 10080, 'booking_horizon_days' => 1, 'cancellation_hours' => 168,
        ]));
        if ($conflict) {
            $response->assertSessionHasErrors('hours')->assertSessionHas('schedule_conflicts', fn ($rows) => array_column($rows, 'id') === [$booking->id]);
            $this->assertEquals($oldHours, DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->get()->toArray());
            $this->assertEquals($oldPolicy, DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->first());
        } else {
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'booking_horizon_days' => 1]);
            $day = CarbonImmutable::parse($booking->appointment_date)->dayOfWeek;
            $saved = DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->where('day_of_week', $day)->sole();
            $this->assertSame($change['start_time'] ?? '08:00', substr($saved->open_time, 0, 5));
            $this->assertSame($change['end_time'] ?? '18:00', substr($saved->close_time, 0, 5));
            $this->assertFalse((bool) $saved->is_closed);
        }
    }

    public function test_hour_changes_preserve_seconds_in_existing_appointment_and_hours(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        // Existing database snapshots can contain seconds even though the form edits minutes.
        $booking->update(['appointment_start_time' => '10:00:30', 'appointment_end_time' => '11:00:30']);
        $booking->items()->update(['item_start_at' => $data['date'].' 10:00:30', 'item_end_at' => $data['date'].' 11:00:30']);
        $day = CarbonImmutable::parse($data['date'])->dayOfWeek;
        $f['staff']->hours()->where('day_of_week', $day)->update(['end_time' => '11:00:45']);
        DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->where('day_of_week', $day)->update(['close_time' => '11:00:45']);
        $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'], $f['staff']]), $this->hoursPayload($f, ['end_time' => '11:00']))
            ->assertSessionHasErrors('schedule');
        $this->put(route('schedule.update', $f['branch']), $this->branchPayload($f, ['end_time' => '11:00']))
            ->assertSessionHasErrors('hours');
    }

    public function test_changes_on_another_weekday_do_not_block_booked_day(): void
    {
        $f = $this->bookingFixture();
        app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $payload = $this->hoursPayload($f);
        $day = CarbonImmutable::parse($this->bookingData($f)['date'])->dayOfWeek;
        $payload['hours'][($day + 1) % 7]['is_off'] = 1;
        $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'], $f['staff']]), $payload)->assertSessionHasNoErrors();
        $this->put(route('schedule.update', $f['branch']), [...$this->branchPayload($f), ...$payload])->assertSessionHasNoErrors();
    }

    public static function leaveIntervals(): array
    {
        return [
            'ends at start' => ['09:00', '10:00', false],
            'starts at end' => ['11:00', '12:00', false],
            'inside appointment' => ['10:15', '10:45', true],
            'encloses appointment' => ['09:00', '12:00', true],
            'left overlap' => ['09:30', '10:30', true],
            'right overlap' => ['10:30', '11:30', true],
        ];
    }

    #[DataProvider('leaveIntervals')]
    public function test_leave_only_blocks_overlapping_bookings(string $start, string $end, bool $conflict): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $response = $this->actingAs($f['user'])->post(route('staff.leave', [$f['branch'], $f['staff']]), [
            'start_at' => $data['date'].'T'.$start, 'end_at' => $data['date'].'T'.$end,
        ]);
        if ($conflict) {
            $response->assertSessionHasErrors('schedule')->assertSessionHas('schedule_conflicts', fn ($rows) => array_column($rows, 'id') === [$booking->id]);
            $this->assertDatabaseCount('staff_leaves', 0);
        } else {
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseCount('staff_leaves', 1);
        }
    }

    public function test_leave_for_another_date_or_worker_is_allowed(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $other = $f['branch']->staff()->create(['full_name' => 'Other', 'status' => 'ACTIVE', 'is_bookable' => true]);
        $nextDate = CarbonImmutable::parse($data['date'])->addDay()->toDateString();
        $this->actingAs($f['user'])->post(route('staff.leave', [$f['branch'], $f['staff']]), [
            'start_at' => $nextDate.'T09:00', 'end_at' => $nextDate.'T12:00',
        ])->assertSessionHasNoErrors();
        $this->post(route('staff.leave', [$f['branch'], $other]), [
            'start_at' => $data['date'].'T09:00', 'end_at' => $data['date'].'T12:00',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('staff_leaves', 2);
    }

    public function test_holiday_only_blocks_valid_bookings_on_that_date(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $otherDate = CarbonImmutable::parse($data['date'])->addDay()->toDateString();
        $this->actingAs($f['user'])->post(route('schedule.holiday', $f['branch']), [
            'date' => $otherDate, 'name' => 'Other day',
        ])->assertSessionHasNoErrors();
        $this->post(route('schedule.holiday', $f['branch']), ['date' => $data['date'], 'name' => 'Conflicting'])
            ->assertSessionHasErrors('hours')->assertSessionHas('schedule_conflicts', fn ($rows) => array_column($rows, 'id') === [$booking->id]);
        $this->assertDatabaseMissing('branch_holidays', ['branch_id' => $f['branch']->id, 'date' => $data['date']]);
    }

    public static function ignoredBookings(): array
    {
        return [
            'hold expired' => ['expired'],
            'hold expires exactly now' => ['expires_now'],
            'cancelled booking' => ['cancelled'],
            'soft deleted booking' => ['deleted'],
            'cancelled item' => ['cancelled_item'],
            'skipped item' => ['skipped_item'],
            'completed item' => ['completed_item'],
            'appointment ended earlier today' => ['past'],
        ];
    }

    #[DataProvider('ignoredBookings')]
    public function test_non_serving_bookings_do_not_block_profile_hours_leave_or_holiday(string $state): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->startOfDay()->addHours(12));
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        match ($state) {
            'expired' => $booking->update(['pending_expires_at' => now()->subMinute()]),
            'expires_now' => $booking->update(['pending_expires_at' => now()]),
            'cancelled' => $booking->update(['status' => 'CANCELLED']),
            'deleted' => $booking->delete(),
            'cancelled_item' => $booking->items()->update(['status' => 'CANCELLED']),
            'skipped_item' => $booking->items()->update(['status' => 'SKIPPED']),
            'completed_item' => $booking->items()->update(['status' => 'COMPLETED']),
            'past' => $booking->update([
                'appointment_date' => now($f['branch']->timezone)->toDateString(),
                'appointment_start_time' => '08:00:00', 'appointment_end_time' => '09:00:00',
            ]),
        };
        if ($state === 'past') {
            $data['date'] = $booking->appointment_date;
            $booking->items()->update(['item_start_at' => $data['date'].' 08:00:00', 'item_end_at' => $data['date'].' 09:00:00']);
        }
        $this->actingAs($f['user'])->put(route('staff.update', [$f['branch'], $f['staff']]), $this->staffPayload($f, [
            'is_bookable' => 0, 'status' => 'INACTIVE', 'service_ids' => [],
        ]))->assertSessionHasNoErrors();
        $this->put(route('staff.hours', [$f['branch'], $f['staff']]), $this->hoursPayload($f, ['is_off' => 1]))->assertSessionHasNoErrors();
        $this->post(route('staff.leave', [$f['branch'], $f['staff']]), [
            'start_at' => $data['date'].'T08:00', 'end_at' => $data['date'].'T12:00',
        ])->assertSessionHasNoErrors();
        $this->put(route('schedule.update', $f['branch']), $this->branchPayload($f, ['is_off' => 1]))->assertSessionHasNoErrors();
        $this->post(route('schedule.holiday', $f['branch']), ['date' => $data['date'], 'name' => 'Closed'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_pending_without_expiry_and_confirmed_with_old_expiry_still_protect_staff(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        foreach ([['pending_expires_at' => null], ['status' => 'CONFIRMED', 'pending_expires_at' => now()->subDay()]] as $changes) {
            $booking->update($changes);
            $this->actingAs($f['user'])->put(route('staff.hours', [$f['branch'], $f['staff']]), $this->hoursPayload($f, ['is_off' => 1]))
                ->assertSessionHasErrors('schedule');
        }
    }

    public function test_ongoing_booking_is_protected_until_service_ends(): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->startOfDay()->addHours(10)->addMinutes(30));
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $date = now($f['branch']->timezone)->toDateString();
        $booking->update(['status' => 'IN_PROGRESS', 'appointment_date' => $date]);
        $booking->items()->update(['status' => 'IN_PROGRESS', 'item_start_at' => $date.' 10:00:00', 'item_end_at' => $date.' 11:00:00']);
        $this->actingAs($f['user'])->post(route('staff.leave', [$f['branch'], $f['staff']]), [
            'start_at' => $date.'T10:30', 'end_at' => $date.'T12:00',
        ])->assertSessionHasErrors('schedule');
        $this->post(route('schedule.holiday', $f['branch']), ['date' => $date, 'name' => 'Closed'])->assertSessionHasErrors('hours');
    }

    public function test_buffer_increase_identifies_both_appointments_and_does_not_save_policy(): void
    {
        $f = $this->bookingFixture();
        $manager = app(BookingManager::class);
        $first = $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        $second = $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '11:00']));
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->branchPayload($f, [], [
            'default_buffer_minutes' => 15,
        ]))->assertSessionHasErrors('hours')->assertSessionHas('schedule_conflicts', function ($rows) use ($first, $second): bool {
            $ids = array_column($rows, 'id');
            sort($ids);
            return $ids === [$first->id, $second->id];
        });
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'default_buffer_minutes' => 0]);
    }

    public function test_buffer_increase_is_allowed_when_existing_gap_is_sufficient(): void
    {
        $f = $this->bookingFixture();
        $manager = app(BookingManager::class);
        $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '11:15']));
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->branchPayload($f, [], [
            'default_buffer_minutes' => 15,
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'default_buffer_minutes' => 15]);
    }

    public function test_conflict_links_open_exact_booking_and_rescheduling_unblocks_staff_hours(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $manager = app(BookingManager::class);
        $booking = $manager->create($f['user'], $f['branch'], $data);
        $safe = $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '14:00']));
        $editUrl = route('staff.edit', [$f['branch'], $f['staff']]);
        $payload = $this->hoursPayload($f, ['start_time' => '12:00']);
        $this->actingAs($f['user'])->from($editUrl)->put(route('staff.hours', [$f['branch'], $f['staff']]), $payload)
            ->assertRedirect($editUrl)->assertSessionHasErrors('schedule')
            ->assertSessionHas('schedule_conflicts', fn ($rows) => array_column($rows, 'id') === [$booking->id]);
        $bookingUrl = route('salon.bookings', [$f['branch'], 'date' => $data['date'], 'booking_id' => $booking->id]);
        $this->get($editUrl)->assertOk()->assertSee($booking->booking_code)->assertDontSee($safe->booking_code)
            ->assertSee($data['date'])->assertSee('10:00')->assertSee('11:00')
            ->assertSee($bookingUrl.'#booking-'.$booking->id);
        $this->get($bookingUrl)->assertOk()->assertSee('id="booking-'.$booking->id.'"', false)
            ->assertSee('data-reschedule-url="'.route('salon.bookings.reschedule', [$f['branch'], $booking]).'"', false)
            ->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$booking->id]);
        $this->patch(route('salon.bookings.reschedule', [$f['branch'], $booking]), [
            'date' => $data['date'], 'time' => '12:00', 'staff_id' => $f['staff']->id,
            'original_start' => $data['date'].' 10:00:00', 'original_staff_id' => $f['staff']->id,
        ])->assertSessionHasNoErrors();
        $this->put(route('staff.hours', [$f['branch'], $f['staff']]), $payload)->assertSessionHasNoErrors();
        $this->assertSame('12:00:00', $booking->fresh()->appointment_start_time);
    }

    public function test_buffer_increase_does_not_conflict_between_different_workers(): void
    {
        $f = $this->bookingFixture();
        $second = $f['branch']->staff()->create(['full_name' => 'Second', 'status' => 'ACTIVE', 'is_bookable' => true]);
        $second->services()->attach($f['service']->id);
        foreach ($f['staff']->hours()->get() as $hour) {
            $second->hours()->create($hour->only(['day_of_week', 'start_time', 'end_time', 'is_off']));
        }
        $manager = app(BookingManager::class);
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['staff_id' => $f['staff']->id]));
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '11:00', 'staff_id' => $second->id]));
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->branchPayload($f, [], [
            'default_buffer_minutes' => 15,
        ]))->assertSessionHasNoErrors();
    }

    public function test_expired_hold_does_not_create_buffer_conflict(): void
    {
        $f = $this->bookingFixture();
        $manager = app(BookingManager::class);
        $expired = $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '11:00']));
        $expired->update(['pending_expires_at' => now()->subMinute()]);
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->branchPayload($f, [], [
            'default_buffer_minutes' => 15,
        ]))->assertSessionHasNoErrors();
    }

    public function test_booking_link_reaches_target_beyond_default_page(): void
    {
        $f = $this->bookingFixture();
        $manager = app(BookingManager::class);
        $template = $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        // Historical rows occupy the first page without consuming appointment capacity.
        for ($index = 0; $index < 15; $index++) {
            $historical = $template->replicate();
            $historical->booking_code = 'HISTORY-'.$index;
            $historical->request_token = (string) \Illuminate\Support\Str::uuid();
            $historical->status = 'CANCELLED';
            $historical->save();
        }
        $target = $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '14:00']));
        $this->actingAs($f['user'])->get(route('salon.bookings', [$f['branch'], 'date' => $target->appointment_date]))
            ->assertViewHas('bookings', fn ($bookings) => ! in_array($target->id, $bookings->getCollection()->modelKeys()));
        $this->get(route('salon.bookings', [$f['branch'], 'date' => $target->appointment_date, 'booking_id' => $target->id]))
            ->assertOk()->assertViewHas('bookings', fn ($bookings) => $bookings->getCollection()->modelKeys() === [$target->id])
            ->assertSee('id="booking-'.$target->id.'"', false);
    }

    public function test_branch_conflicts_render_links_on_schedule_form(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $editUrl = route('schedule.edit', $f['branch']);
        $this->actingAs($f['user'])->from($editUrl)->post(route('schedule.holiday', $f['branch']), [
            'date' => $data['date'], 'name' => 'Closed',
        ])->assertRedirect($editUrl)->assertSessionHasErrors('hours');
        $this->get($editUrl)->assertOk()->assertSee($booking->booking_code)
            ->assertSee(route('salon.bookings', [$f['branch'], 'date' => $data['date'], 'booking_id' => $booking->id]).'#booking-'.$booking->id);
    }

    public function test_specific_booking_link_cannot_escape_authorized_branch(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $otherBranch = $f['branch']->replicate();
        $otherBranch->save();
        $this->actingAs($f['user'])->get(route('salon.bookings', [$otherBranch, 'booking_id' => $booking->id]))
            ->assertOk()->assertDontSee($booking->booking_code);
        $this->get(route('salon.bookings', [$f['branch'], 'booking_id' => 'invalid']))->assertSessionHasErrors('booking_id');
    }
}
