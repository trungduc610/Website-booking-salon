<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\BookingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function moveData(Booking $booking): array
    {
        return ['date' => $booking->appointment_date, 'time' => '12:00',
            'original_start' => $booking->appointment_date.' '.$booking->appointment_start_time,
            'original_staff_id' => $booking->items()->first()->staff_id,
            'staff_id' => $booking->items()->first()->staff_id];
    }

    public function test_customer_move_preserves_price_hold_and_snapshot_and_records_history(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $expiry = $booking->fresh()->pending_expires_at;
        $this->actingAs($f['user'])->patch(route('bookings.reschedule', $booking), $this->moveData($booking))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('12:00:00', $booking->fresh()->appointment_start_time);
        $this->assertSame($expiry, $booking->fresh()->pending_expires_at);
        $this->assertEquals($booking->final_amount, $booking->fresh()->final_amount);
        $this->assertDatabaseHas('booking_services', ['booking_id' => $booking->id, 'item_start_at' => $booking->appointment_date.' 12:00:00', 'price_at_booking' => '123456.78']);
        $this->assertDatabaseCount('booking_status_histories', 2);
    }

    public function test_conflict_and_stale_request_leave_current_booking_unchanged(): void
    {
        $f = $this->bookingFixture();
        $manager = app(BookingManager::class);
        $booking = $manager->create($f['user'], $f['branch'], $this->bookingData($f));
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['time' => '12:00']));
        $this->actingAs($f['user'])->patch(route('bookings.reschedule', $booking), $this->moveData($booking))->assertSessionHasErrors('time');
        $this->assertSame('10:00:00', $booking->fresh()->appointment_start_time);
        $this->patch(route('bookings.reschedule', $booking), [...$this->moveData($booking), 'time' => '14:00'])->assertSessionHasNoErrors();
        $this->patch(route('bookings.reschedule', $booking), [...$this->moveData($booking), 'time' => '15:00'])->assertSessionHasErrors('date');
        $this->assertSame('14:00:00', $booking->fresh()->appointment_start_time);
    }

    public function test_expired_holds_changed_duration_and_other_customers_are_rejected(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $f['service']->update(['duration_minutes' => 90]);
        $this->actingAs($f['user'])->patch(route('bookings.reschedule', $booking), $this->moveData($booking))->assertSessionHasErrors('date');
        $f['service']->update(['duration_minutes' => 60]);
        $booking->update(['pending_expires_at' => now()->subMinute()]);
        $this->patch(route('bookings.reschedule', $booking), $this->moveData($booking))->assertSessionHasErrors('date');
        $other = \App\Models\User::create(['full_name' => 'Other', 'email' => 'move@example.test', 'password_hash' => $f['user']->password_hash]);
        $other->customerProfile()->create();
        $this->actingAs($other)->patch(route('bookings.reschedule', $booking), $this->moveData($booking))->assertForbidden();
        $this->patch(route('salon.bookings.reschedule', [$f['branch'], $booking]), $this->moveData($booking))->assertForbidden();
    }

    public function test_salon_can_move_to_an_available_qualified_staff_member(): void
    {
        $f = $this->bookingFixture();
        $staff = $f['branch']->staff()->create(['full_name' => 'Second', 'status' => 'ACTIVE', 'is_bookable' => true]);
        $staff->services()->attach($f['service']->id);
        foreach ($f['staff']->hours()->get() as $hour) {
            $staff->hours()->create($hour->only(['day_of_week','start_time','end_time','is_off']));
        }
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->actingAs($f['user'])->patch(route('salon.bookings.reschedule', [$f['branch'], $booking]), [...$this->moveData($booking), 'staff_id' => $staff->id])->assertSessionHasNoErrors();
        $this->assertEquals($staff->id, $booking->items()->first()->staff_id);
    }
}
