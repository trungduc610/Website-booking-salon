<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\Availability;
use App\Services\BookingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    public function test_booking_persists_snapshot_and_repeated_submission_is_idempotent(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $this->actingAs($f['user'])->post(route('bookings.store', $f['branch']), [...$data, 'price' => 1])->assertRedirect();
        $this->post(route('bookings.store', $f['branch']), $data)->assertRedirect();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('booking_services', ['price_at_booking' => '123456.78', 'staff_id' => $f['staff']->id]);
        $this->get(route('bookings.show', Booking::sole()))->assertOk()->assertSee('Haircut');
    }

    public function test_identical_enclosed_and_partial_overlaps_are_rejected_but_adjacent_is_free(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        foreach (['10:00', '09:30', '10:30'] as $time) {
            $plan = app(Availability::class)->plan($f['branch'], [...$data, 'time' => $time]);
            $this->assertCount(0, $plan['staff']);
        }
        $this->assertCount(1, app(Availability::class)->plan($f['branch'], [...$data, 'time' => '11:00'])['staff']);
        DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->update(['default_buffer_minutes' => 15]);
        $this->assertCount(0, app(Availability::class)->plan($f['branch'], [...$data, 'time' => '11:00'])['staff']);
        $this->assertCount(1, app(Availability::class)->plan($f['branch'], [...$data, 'time' => '11:15'])['staff']);
    }

    public function test_any_staff_assigns_distinct_available_workers_until_capacity_is_full(): void
    {
        $f = $this->bookingFixture();
        $second = $f['branch']->staff()->create(['full_name' => 'Second', 'status' => 'ACTIVE', 'is_bookable' => true]);
        $second->services()->attach($f['service']->id);
        foreach ($f['staff']->hours()->get() as $hour) {
            $second->hours()->create($hour->only(['day_of_week','start_time','end_time','is_off']));
        }
        $manager = app(BookingManager::class);
        $firstBooking = $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        $secondBooking = $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->assertNotSame($firstBooking->items()->first()->staff_id, $secondBooking->items()->first()->staff_id);
        $this->actingAs($f['user'])->post(route('bookings.store', $f['branch']), $this->bookingData($f))->assertSessionHasErrors('time');
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_leave_and_missing_skill_exclude_staff(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $leave = $f['staff']->leaves()->create(['start_at' => $data['date'].' 09:00:00', 'end_at' => $data['date'].' 12:00:00', 'status' => 'APPROVED']);
        $this->assertCount(0, app(Availability::class)->plan($f['branch'], $data)['staff']);
        $leave->update(['status' => 'CANCELLED']);
        $this->assertCount(1, app(Availability::class)->plan($f['branch'], $data)['staff']);
        $f['staff']->services()->detach();
        $this->assertCount(0, app(Availability::class)->plan($f['branch'], $data)['staff']);
    }

    public function test_working_hours_holidays_and_duplicate_services_are_validated(): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user']);
        $data = $this->bookingData($f);
        $this->post(route('bookings.store', $f['branch']), [...$data, 'time' => '18:00'])->assertSessionHasErrors('time');
        $this->post(route('bookings.store', $f['branch']), [...$data, 'service_ids' => [$f['service']->id,$f['service']->id]])->assertSessionHasErrors('service_ids.0');
        DB::table('branch_holidays')->insert(['branch_id' => $f['branch']->id,'date' => $data['date'],'name' => 'Closed','is_closed' => 1]);
        $this->post(route('bookings.store', $f['branch']), $data)->assertSessionHasErrors('time');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_expired_hold_releases_capacity_and_cannot_be_confirmed(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $booking->update(['pending_expires_at' => now()->subMinute()]);
        $this->assertCount(1, app(Availability::class)->plan($f['branch'], $data)['staff']);
        $this->actingAs($f['user'])->patch(route('salon.bookings.status', [$f['branch'],$booking]), ['status' => 'CONFIRMED'])->assertSessionHasErrors('status');
    }

    public function test_state_machine_rejects_skipping_and_cancel_releases_capacity(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $this->actingAs($f['user'])->patch(route('salon.bookings.status', [$f['branch'],$booking]), ['status' => 'COMPLETED'])->assertSessionHasErrors('status');
        $this->patch(route('bookings.cancel', $booking))->assertRedirect();
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertCount(1, app(Availability::class)->plan($f['branch'], $data)['staff']);
        $this->assertDatabaseCount('booking_status_histories', 2);
    }

    public function test_other_customer_cannot_view_or_cancel_booking(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $other = \App\Models\User::create(['full_name' => 'Other','email' => 'other@example.test','password_hash' => $f['user']->password_hash]);
        $other->customerProfile()->create();
        $this->actingAs($other)->get(route('bookings.show', $booking))->assertForbidden();
        $this->patch(route('bookings.cancel', $booking))->assertForbidden();
        $this->assertSame('PENDING', $booking->fresh()->status);
    }

    public function test_booking_pages_and_availability_render(): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user']);
        $this->get(route('salons.index'))->assertOk();
        $this->get(route('bookings.create', $f['branch']))->assertOk();
        $this->get(route('bookings.index'))->assertOk();
        $this->get(route('salon.bookings', $f['branch']))->assertOk();
        $this->getJson(route('bookings.availability', $f['branch']).'?'.http_build_query($this->bookingData($f)))->assertOk()->assertJson(['available' => true]);
    }

    public function test_expiration_command_records_history_once(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $booking->update(['pending_expires_at' => now()->subMinute()]);
        $this->artisan('glowbook:expire-bookings')->assertSuccessful();
        $this->artisan('glowbook:expire-bookings')->assertSuccessful();
        $this->assertSame('EXPIRED', $booking->fresh()->status);
        $this->assertDatabaseCount('booking_status_histories', 2);
    }

    public function test_automatic_confirmation_has_no_expiring_hold(): void
    {
        $f = $this->bookingFixture();
        $f['branch']->update(['booking_confirmation_mode' => 'AUTO_CONFIRMATION']);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->assertSame('CONFIRMED', $booking->status);
        $this->assertNull($booking->pending_expires_at);
    }
}
