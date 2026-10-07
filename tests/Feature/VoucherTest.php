<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\BookingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function voucher(array $f, array $extra = []): Voucher
    {
        return Voucher::create(array_merge(['business_id' => $f['business']->id, 'code' => 'SAVE10', 'name' => 'Test',
            'discount_type' => 'PERCENTAGE', 'discount_value' => '10.00', 'min_order_value' => '0.00',
            'total_quantity' => 2, 'used_quantity' => 0, 'max_usage_per_customer' => 1,
            'start_date' => now()->subDay(), 'end_date' => now()->addWeek(), 'status' => 'ACTIVE'], $extra));
    }

    public function test_discount_is_snapshotted_and_replay_does_not_consume_twice(): void
    {
        $f = $this->bookingFixture();
        $v = $this->voucher($f);
        $data = $this->bookingData($f, ['voucher_code' => 'save10']);
        $manager = app(BookingManager::class);
        $booking = $manager->create($f['user'], $f['branch'], $data);
        $this->assertSame('12345.67', (string) $booking->voucher_discount_amount);
        $this->assertSame('111111.11', (string) $booking->final_amount);
        $this->assertSame($booking->id, $manager->create($f['user'], $f['branch'], $data)->id);
        $this->assertSame(1, $v->fresh()->used_quantity);
        $manager->transition($f['user'], $booking, 'CANCELLED', true);
        $this->assertSame(0, $v->fresh()->used_quantity);
    }

    public function test_fixed_discount_cannot_make_amount_negative(): void
    {
        $f = $this->bookingFixture();
        $this->voucher($f, ['discount_type' => 'FIXED_AMOUNT', 'discount_value' => '999999.00']);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f, ['voucher_code' => 'SAVE10']));
        $this->assertSame('0.00', (string) $booking->final_amount);
    }

    public function test_inactive_expired_and_exhausted_codes_are_rejected(): void
    {
        $f = $this->bookingFixture();
        $v = $this->voucher($f);
        $this->actingAs($f['user']);
        foreach ([['status' => 'INACTIVE'], ['status' => 'ACTIVE', 'end_date' => now()->subMinute()],
            ['end_date' => now()->addWeek(), 'used_quantity' => 2]] as $changes) {
            $v->update($changes);
            $this->post(route('bookings.store', $f['branch']), $this->bookingData($f, ['voucher_code' => 'SAVE10']))
                ->assertSessionHasErrors('voucher_code');
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_invalid_voucher_rolls_back_booking_and_usage(): void
    {
        $f = $this->bookingFixture();
        $v = $this->voucher($f, ['min_order_value' => '999999.00']);
        $this->actingAs($f['user'])->post(route('bookings.store', $f['branch']), $this->bookingData($f, ['voucher_code' => 'SAVE10']))->assertSessionHasErrors('voucher_code');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame(0, $v->fresh()->used_quantity);
    }

    public function test_customer_usage_limit_and_cap_are_enforced(): void
    {
        $f = $this->bookingFixture();
        $v = $this->voucher($f, ['max_discount' => '5000.00']);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f, ['voucher_code' => 'SAVE10']));
        $this->assertSame('5000.00', (string) $booking->voucher_discount_amount);
        $this->actingAs($f['user'])->post(route('bookings.store', $f['branch']), $this->bookingData($f, ['time' => '14:00', 'voucher_code' => 'SAVE10']))->assertSessionHasErrors('voucher_code');
        $this->assertSame(1, $v->fresh()->used_quantity);
    }

    public function test_expiry_releases_voucher_only_once(): void
    {
        $f = $this->bookingFixture();
        $v = $this->voucher($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f, ['voucher_code' => 'SAVE10']));
        $booking->update(['pending_expires_at' => now()->subMinute()]);
        $this->artisan('glowbook:expire-bookings')->assertSuccessful();
        $this->artisan('glowbook:expire-bookings')->assertSuccessful();
        $this->assertSame(0, $v->fresh()->used_quantity);
        $this->assertSame('EXPIRED', $booking->fresh()->status);
    }

    public function test_owner_can_create_and_toggle_but_customer_cannot(): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user'])->get(route('vouchers.index', $f['branch']))->assertOk();
        $this->post(route('vouchers.store', $f['branch']), ['code' => 'hello', 'name' => 'Hello', 'discount_type' => 'PERCENTAGE',
            'discount_value' => '10', 'min_order_value' => '0', 'total_quantity' => 5, 'max_usage_per_customer' => 1,
            'start_date' => now()->format('Y-m-d\TH:i'), 'end_date' => now()->addWeek()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $v = Voucher::where('code', 'HELLO')->sole();
        $this->patch(route('vouchers.toggle', [$f['branch'], $v]), ['status' => 'INACTIVE'])->assertSessionHasNoErrors();
        $this->assertSame('INACTIVE', $v->fresh()->status);
        $f['user']->roles()->detach();
        $f['user']->unsetRelation('roles');
        $this->get(route('vouchers.index', $f['branch']))->assertForbidden();
    }
}
