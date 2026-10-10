<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\BookingManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class ReschedulePolicyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function policyPayload(int $hours = 12): array
    {
        return [
            'hours' => array_map(fn ($day) => [
                'day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '18:00', 'is_off' => 0,
            ], range(0, 6)),
            'lead_time_minutes' => 0, 'booking_horizon_days' => 90,
            'default_buffer_minutes' => 0, 'cancellation_hours' => 24, 'reschedule_hours' => $hours,
        ];
    }

    private function assertPolicyInput(array $f, string $expected): void
    {
        $response = $this->get(route('schedule.edit', $f['branch']))->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $input = (new \DOMXPath($document))->query('//input[@name="reschedule_hours"]')->item(0);
        $this->assertNotNull($input);
        $this->assertSame($expected, $input->getAttribute('value'));
        $this->assertSame('0', $input->getAttribute('min'));
        $this->assertSame('168', $input->getAttribute('max'));
        $this->assertTrue($input->hasAttribute('required'));
        $response->assertSee('Thời hạn khách tự đổi lịch trước giờ hẹn')
            ->assertSee('Đặt 0 để khách có thể tự đổi lịch đến giờ bắt đầu hẹn.');
    }

    public static function validHours(): array
    {
        return ['zero' => [0], 'custom deadline' => [36], 'maximum' => [168]];
    }

    #[DataProvider('validHours')]
    public function test_owner_can_save_and_reload_reschedule_hours(int $hours): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user']);
        $this->assertPolicyInput($f, '12');
        $otherBranch = $f['branch']->replicate();
        $otherBranch->save();
        DB::table('branch_booking_policies')->insert(['branch_id' => $otherBranch->id, 'reschedule_hours' => 6]);
        $this->put(route('schedule.update', $f['branch']), $this->policyPayload($hours))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'reschedule_hours' => $hours]);
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $otherBranch->id, 'reschedule_hours' => 6]);
        $this->assertPolicyInput($f, (string) $hours);
    }

    public function test_reschedule_hours_can_be_saved_when_policy_row_is_missing(): void
    {
        $f = $this->bookingFixture();
        DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->delete();
        $this->actingAs($f['user']);
        $this->assertPolicyInput($f, '12');
        $this->put(route('schedule.update', $f['branch']), $this->policyPayload(36))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'reschedule_hours' => 36]);
        $this->assertDatabaseCount('branch_booking_policies', 1);
    }

    public static function invalidHours(): array
    {
        return [
            'negative' => [-1], 'over maximum' => [169], 'fractional' => [1.5],
            'text' => ['invalid'], 'null' => [null], 'empty' => [''],
        ];
    }

    #[DataProvider('invalidHours')]
    public function test_invalid_reschedule_hours_do_not_change_hours_or_policy(mixed $value): void
    {
        $f = $this->bookingFixture();
        $originalPolicy = DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->first();
        $originalHours = DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->get()->toArray();
        $payload = $this->policyPayload();
        $payload['reschedule_hours'] = $value;
        $payload['hours'][0]['start_time'] = '09:00';
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $payload)
            ->assertSessionHasErrors('reschedule_hours');
        $this->assertEquals($originalPolicy, DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->first());
        $this->assertEquals($originalHours, DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->get()->toArray());
    }

    public function test_reschedule_hours_is_required_and_invalid_input_is_preserved_on_form(): void
    {
        $f = $this->bookingFixture();
        $payload = $this->policyPayload();
        unset($payload['reschedule_hours']);
        $editUrl = route('schedule.edit', $f['branch']);
        $this->actingAs($f['user'])->from($editUrl)->put(route('schedule.update', $f['branch']), $payload)
            ->assertSessionHasErrors('reschedule_hours');
        $this->from($editUrl)->put(route('schedule.update', $f['branch']), [...$payload, 'reschedule_hours' => 169])
            ->assertRedirect($editUrl)->assertSessionHasErrors('reschedule_hours');
        $this->assertPolicyInput($f, '169');
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'reschedule_hours' => 12]);
    }

    public function test_staff_without_update_permission_cannot_change_reschedule_hours(): void
    {
        $f = $this->bookingFixture();
        $f['user']->roles()->detach();
        $f['user']->roles()->attach(Role::where('code', 'STAFF')->sole()->id, [
            'business_id' => $f['business']->id, 'branch_id' => $f['branch']->id,
        ]);
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->policyPayload(36))->assertForbidden();
        $this->assertDatabaseHas('branch_booking_policies', ['branch_id' => $f['branch']->id, 'reschedule_hours' => 12]);
    }

    public static function deadlineCases(): array
    {
        return [
            'before custom cutoff' => [36, -1, true],
            'exactly at custom cutoff' => [36, 0, true],
            'after custom cutoff' => [36, 1, false],
            'zero before start' => [0, -1, true],
            'zero at start' => [0, 0, true],
            'zero after start' => [0, 1, false],
        ];
    }

    #[DataProvider('deadlineCases')]
    public function test_customer_rescheduling_respects_saved_deadline(int $hours, int $seconds, bool $allowed): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->policyPayload($hours))
            ->assertSessionHasNoErrors();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $booking->update(['status' => 'CONFIRMED', 'pending_expires_at' => null]);
        $original = $booking->fresh()->getAttributes();
        $originalItems = $booking->items()->get()->toArray();
        $start = CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_start_time, $f['branch']->timezone);
        $this->travelTo($start->subHours($hours)->addSeconds($seconds));
        $newDate = $start->addDay()->toDateString();
        $response = $this->patch(route('bookings.reschedule', $booking), [
            'date' => $newDate, 'time' => '12:00',
            'original_start' => $booking->appointment_date.' '.$booking->appointment_start_time,
            'original_staff_id' => $f['staff']->id,
        ]);
        if ($allowed) {
            $response->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($newDate, $booking->fresh()->appointment_date);
            $this->assertSame('12:00:00', $booking->fresh()->appointment_start_time);
            $this->assertDatabaseHas('booking_services', ['booking_id' => $booking->id, 'item_start_at' => $newDate.' 12:00:00']);
            $this->assertDatabaseCount('booking_status_histories', 2);
        } else {
            $response->assertSessionHasErrors(['date' => 'Đã quá thời hạn tự đổi lịch. Vui lòng liên hệ salon.']);
            $this->assertSame($original, $booking->fresh()->getAttributes());
            $this->assertSame($originalItems, $booking->items()->get()->toArray());
            $this->assertDatabaseCount('booking_status_histories', 1);
        }
    }

    public function test_salon_can_reschedule_after_customer_deadline(): void
    {
        $f = $this->bookingFixture();
        $this->actingAs($f['user'])->put(route('schedule.update', $f['branch']), $this->policyPayload(36))->assertSessionHasNoErrors();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $booking->update(['status' => 'CONFIRMED', 'pending_expires_at' => null]);
        $start = CarbonImmutable::parse($booking->appointment_date.' '.$booking->appointment_start_time, $f['branch']->timezone);
        $this->travelTo($start->subHours(36)->addSecond());
        $this->patch(route('salon.bookings.reschedule', [$f['branch'], $booking]), [
            'date' => $start->addDay()->toDateString(), 'time' => '12:00', 'staff_id' => $f['staff']->id,
            'original_start' => $booking->appointment_date.' '.$booking->appointment_start_time,
            'original_staff_id' => $f['staff']->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('12:00:00', $booking->fresh()->appointment_start_time);
    }
}
