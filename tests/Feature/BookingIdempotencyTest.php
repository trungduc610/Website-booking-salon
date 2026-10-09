<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\BookingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class BookingIdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function voucher(array $fixture): Voucher
    {
        return Voucher::create([
            'business_id' => $fixture['business']->id, 'code' => 'SAVE10', 'name' => 'Test',
            'discount_type' => 'PERCENTAGE', 'discount_value' => '10.00', 'min_order_value' => '0.00',
            'total_quantity' => 1, 'used_quantity' => 0, 'max_usage_per_customer' => 1,
            'start_date' => now()->subDay(), 'end_date' => now()->addWeek(), 'status' => 'ACTIVE',
        ]);
    }

    public static function changedFields(): array
    {
        return [
            'date' => ['date'],
            'time' => ['time'],
            'services' => ['service_ids'],
            'staff' => ['staff_id'],
            'automatic staff' => ['automatic_staff'],
            'voucher' => ['voucher_code'],
            'removed voucher' => ['removed_voucher'],
            'note' => ['note'],
            'removed note' => ['removed_note'],
        ];
    }

    #[DataProvider('changedFields')]
    public function test_changed_payload_conflicts_without_mutating_booking_or_voucher(string $field): void
    {
        $f = $this->bookingFixture();
        $voucher = $this->voucher($f);
        $data = $this->bookingData($f, [
            'staff_id' => $f['staff']->id, 'voucher_code' => 'SAVE10', 'note' => 'Original note',
        ]);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $original = $booking->fresh()->getAttributes();
        $items = $booking->items()->get()->toArray();
        $changes = match ($field) {
            'date' => ['date' => now($f['branch']->timezone)->addDays(4)->toDateString()],
            'time' => ['time' => '12:00'],
            'service_ids' => ['service_ids' => [$f['service']->id + 100]],
            'staff_id' => ['staff_id' => $f['staff']->id + 100],
            'automatic_staff' => ['staff_id' => null],
            'voucher_code' => ['voucher_code' => 'OTHER'],
            'removed_voucher' => ['voucher_code' => null],
            'note' => ['note' => 'Changed note'],
            'removed_note' => ['note' => null],
        };

        $this->actingAs($f['user'])->postJson(route('bookings.store', $f['branch']), array_replace($data, $changes))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Mã yêu cầu đã được sử dụng với dữ liệu đặt lịch khác. Vui lòng dùng mã yêu cầu mới.');

        $this->assertSame($original, $booking->fresh()->getAttributes());
        $this->assertSame($items, $booking->items()->get()->toArray());
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_status_histories', 1);
        $this->assertSame(1, $voucher->fresh()->used_quantity);
    }

    public function test_equivalent_payload_returns_same_booking_even_when_voucher_is_no_longer_available(): void
    {
        $f = $this->bookingFixture();
        $voucher = $this->voucher($f);
        $data = $this->bookingData($f, [
            'staff_id' => (string) $f['staff']->id,
            'service_ids' => [5 => (string) $f['service']->id],
            'voucher_code' => ' save10 ', 'note' => "  Chăm sóc tóc\r\nNhẹ nhàng  ",
        ]);
        $manager = app(BookingManager::class);
        $booking = $manager->create($f['user'], $f['branch'], $data);
        $voucher->update(['status' => 'INACTIVE']);
        $f['service']->update(['status' => 'INACTIVE']);
        $retry = array_replace($data, [
            'staff_id' => $f['staff']->id, 'service_ids' => [$f['service']->id],
            'voucher_code' => 'SAVE10', 'note' => "Chăm sóc tóc\nNhẹ nhàng",
        ]);

        $this->assertSame($booking->id, $manager->create($f['user'], $f['branch'], $retry)->id);
        $this->actingAs($f['user'])->post(route('bookings.store', $f['branch']), $retry)
            ->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $booking));
        $this->assertSame("Chăm sóc tóc\nNhẹ nhàng", $booking->fresh()->note);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_services', 1);
        $this->assertDatabaseCount('booking_status_histories', 1);
        $this->assertSame(1, $voucher->fresh()->used_quantity);
    }

    public function test_omitted_null_and_empty_optional_fields_are_equivalent_but_explicit_staff_and_added_voucher_are_not(): void
    {
        $f = $this->bookingFixture();
        $voucher = $this->voucher($f);
        $data = $this->bookingData($f);
        $manager = app(BookingManager::class);
        $booking = $manager->create($f['user'], $f['branch'], $data);
        foreach ([null, '', '  '] as $empty) {
            $retry = [...$data, 'staff_id' => null, 'voucher_code' => $empty, 'note' => $empty];
            $this->assertSame($booking->id, $manager->create($f['user'], $f['branch'], $retry)->id);
            $this->actingAs($f['user'])->post(route('bookings.store', $f['branch']), $retry)
                ->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $booking));
        }
        // Choosing the automatically assigned worker is still a different original request.
        foreach ([['staff_id' => $f['staff']->id], ['voucher_code' => 'SAVE10'], ['note' => 'New note']] as $changes) {
            $this->postJson(route('bookings.store', $f['branch']), array_replace($data, $changes))->assertStatus(409);
        }
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_status_histories', 1);
        $this->assertSame(0, $voucher->fresh()->used_quantity);
    }

    public function test_service_order_is_preserved_and_reordering_adding_or_removing_services_conflicts(): void
    {
        $f = $this->bookingFixture();
        $second = $f['service']->replicate();
        $second->name = 'Second service';
        $second->save();
        $f['staff']->services()->attach($second->id);
        $data = $this->bookingData($f, ['service_ids' => [$f['service']->id, $second->id]]);
        $manager = app(BookingManager::class);
        $booking = $manager->create($f['user'], $f['branch'], $data);
        $this->assertSame($data['service_ids'], $booking->items()->orderBy('sort_order')->pluck('service_id')->all());
        $this->assertSame($booking->id, $manager->create($f['user'], $f['branch'], [
            ...$data, 'service_ids' => [3 => (string) $f['service']->id, 7 => (string) $second->id],
        ])->id);
        foreach ([array_reverse($data['service_ids']), [$f['service']->id], [...$data['service_ids'], $second->id + 100]] as $ids) {
            $this->actingAs($f['user'])->postJson(route('bookings.store', $f['branch']), [...$data, 'service_ids' => $ids])
                ->assertStatus(409);
        }
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_services', 2);
        $this->assertDatabaseCount('booking_status_histories', 1);
    }

    public function test_replay_uses_original_payload_after_rescheduling_date_time_and_staff(): void
    {
        $f = $this->bookingFixture();
        $voucher = $this->voucher($f);
        $data = $this->bookingData($f, ['staff_id' => $f['staff']->id, 'voucher_code' => 'SAVE10']);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $hash = $booking->request_payload_hash;
        $second = $f['branch']->staff()->create(['full_name' => 'Second', 'status' => 'ACTIVE', 'is_bookable' => true]);
        $second->services()->attach($f['service']->id);
        foreach ($f['staff']->hours()->get() as $hour) {
            $second->hours()->create($hour->only(['day_of_week', 'start_time', 'end_time', 'is_off']));
        }
        $newDate = now($f['branch']->timezone)->addDays(4)->toDateString();
        $this->actingAs($f['user'])->patch(route('salon.bookings.reschedule', [$f['branch'], $booking]), [
            'date' => $newDate, 'time' => '12:00', 'staff_id' => $second->id,
            'original_start' => $booking->appointment_date.' '.$booking->appointment_start_time,
            'original_staff_id' => $f['staff']->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->post(route('bookings.store', $f['branch']), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $booking));
        $this->postJson(route('bookings.store', $f['branch']), [
            ...$data, 'date' => $newDate, 'time' => '12:00', 'staff_id' => $second->id,
        ])->assertStatus(409);
        $replayed = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $this->assertSame($booking->id, $replayed->id);
        $this->assertSame($newDate, $replayed->appointment_date);
        $this->assertSame('12:00:00', $replayed->appointment_start_time);
        $this->assertSame($second->id, $replayed->items()->sole()->staff_id);
        $this->assertSame($hash, $replayed->request_payload_hash);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_status_histories', 2);
        $this->assertSame(1, $voucher->fresh()->used_quantity);
    }

    public function test_legacy_token_without_original_payload_returns_a_clear_conflict(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $booking->update(['request_payload_hash' => null]);
        $this->actingAs($f['user'])->postJson(route('bookings.store', $f['branch']), $data)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Không thể xác minh dữ liệu ban đầu của mã yêu cầu này. Vui lòng kiểm tra lịch đã đặt trước khi gửi yêu cầu mới.');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertNull($booking->fresh()->request_payload_hash);
    }

    public function test_same_payload_cannot_reuse_token_for_another_customer_or_branch(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $other = User::create(['full_name' => 'Other', 'email' => 'replay@example.test', 'password_hash' => $f['user']->password_hash]);
        $other->customerProfile()->create();
        $this->actingAs($other)->postJson(route('bookings.store', $f['branch']), $data)->assertStatus(409);
        $otherBranch = $f['branch']->replicate();
        $otherBranch->save();
        $this->actingAs($f['user'])->postJson(route('bookings.store', $otherBranch), $data)->assertStatus(409);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_status_histories', 1);
    }
}
