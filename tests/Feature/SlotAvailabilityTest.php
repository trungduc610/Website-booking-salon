<?php

namespace Tests\Feature;

use App\Services\Availability;
use App\Services\BookingManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class SlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    private function slotsUrl(array $f, array $data): string
    {
        return route('bookings.slots', $f['branch']).'?'.http_build_query($data);
    }

    private function assertMatchesIndividualPlans(array $f, array $data, array $slots): void
    {
        foreach ($slots as $slot) {
            try {
                $plan = app(Availability::class)->plan($f['branch'], [...$data, 'time' => $slot['time']]);
                $available = $plan['staff']->isNotEmpty();
                $expected = ['time' => $slot['time'], 'available' => $available,
                    'message' => $available ? 'Còn chỗ' : 'Không còn chuyên viên phù hợp'];
            } catch (ValidationException $e) {
                $expected = ['time' => $slot['time'], 'available' => false,
                    'message' => collect($e->errors())->flatten()->first()];
            }
            $this->assertSame($expected, $slot, $slot['time']);
        }
    }

    public static function scenarios(): array
    {
        $cases = [
            'normal', 'buffer', 'expired hold', 'hold without expiry', 'confirmed with old expiry',
            'missing skill', 'day off', 'short shift', 'leave', 'selected worker', 'any worker', 'missing selection',
            'manual assignment', 'horizon', 'lead time', 'long service', 'inactive service', 'past and inactive service',
        ];
        return array_combine($cases, array_map(fn ($case) => [$case], $cases));
    }

    #[DataProvider('scenarios')]
    public function test_bulk_slots_match_individual_plans_for_all_choices(string $scenario): void
    {
        $this->travelTo(now('Asia/Ho_Chi_Minh')->startOfDay()->addHours(9)->addMinutes(15));
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $manager = app(BookingManager::class);
        if (in_array($scenario, ['buffer', 'expired hold', 'hold without expiry', 'confirmed with old expiry', 'selected worker', 'any worker'])) {
            $booking = $manager->create($f['user'], $f['branch'], $data);
            if ($scenario === 'buffer') {
                DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->update(['default_buffer_minutes' => 30]);
            } elseif ($scenario === 'expired hold') {
                $booking->update(['pending_expires_at' => now()]);
            } elseif ($scenario === 'hold without expiry') {
                $booking->update(['pending_expires_at' => null]);
            } elseif ($scenario === 'confirmed with old expiry') {
                $booking->update(['status' => 'CONFIRMED', 'pending_expires_at' => now()->subMinute()]);
            } else {
                $second = $f['branch']->staff()->create(['full_name' => 'Second', 'status' => 'ACTIVE', 'is_bookable' => true]);
                $second->services()->attach($f['service']->id);
                foreach ($f['staff']->hours()->get() as $hour) {
                    $second->hours()->create($hour->only(['day_of_week', 'start_time', 'end_time', 'is_off']));
                }
                if ($scenario === 'selected worker') {
                    $data['staff_id'] = $f['staff']->id;
                }
            }
        }
        if ($scenario === 'missing skill') {
            $f['staff']->services()->detach();
        } elseif ($scenario === 'day off') {
            $f['staff']->hours()->update(['is_off' => true]);
        } elseif ($scenario === 'short shift') {
            $f['staff']->hours()->update(['start_time' => '09:00', 'end_time' => '12:00']);
        } elseif ($scenario === 'leave') {
            $f['staff']->leaves()->create(['start_at' => $data['date'].' 10:00:00', 'end_at' => $data['date'].' 11:00:00', 'status' => 'APPROVED']);
            $f['staff']->leaves()->create(['start_at' => $data['date'].' 12:00:00', 'end_at' => $data['date'].' 13:00:00', 'status' => 'CANCELLED']);
        } elseif ($scenario === 'missing selection') {
            $f['branch']->update(['staff_assignment_mode' => 'CUSTOMER_SELECTS_STAFF']);
        } elseif ($scenario === 'manual assignment') {
            $f['branch']->update(['staff_assignment_mode' => 'MANUAL_ASSIGN_BY_RECEPTIONIST']);
        } elseif ($scenario === 'horizon') {
            DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->update(['booking_horizon_days' => 1]);
        } elseif (in_array($scenario, ['lead time', 'past and inactive service'])) {
            $data['date'] = now($f['branch']->timezone)->toDateString();
            DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->update(['lead_time_minutes' => 45]);
        } elseif ($scenario === 'long service') {
            $f['service']->update(['duration_minutes' => 150]);
        }
        if (in_array($scenario, ['inactive service', 'past and inactive service'])) {
            $f['service']->update(['status' => 'INACTIVE']);
        }

        $slots = $this->actingAs($f['user'])->getJson($this->slotsUrl($f, $data))->assertOk()->json('slots');
        $this->assertCount(20, $slots);
        $this->assertMatchesIndividualPlans($f, $data, $slots);
        $atTen = collect($slots)->firstWhere('time', '10:00');
        if (in_array($scenario, ['normal', 'expired hold', 'short shift', 'lead time', 'long service', 'any worker'])) {
            $this->assertTrue($atTen['available']);
        } else {
            $this->assertFalse($atTen['available']);
        }
        if ($scenario === 'buffer') {
            $this->assertFalse(collect($slots)->firstWhere('time', '11:00')['available']);
            $this->assertTrue(collect($slots)->firstWhere('time', '11:30')['available']);
        } elseif ($scenario === 'leave') {
            $this->assertTrue(collect($slots)->firstWhere('time', '11:00')['available']);
            $this->assertTrue(collect($slots)->firstWhere('time', '12:00')['available']);
        } elseif ($scenario === 'past and inactive service') {
            $this->assertSame('Ngày giờ nằm ngoài thời gian nhận đặt lịch.', collect($slots)->firstWhere('time', '09:30')['message']);
            $this->assertSame('Có dịch vụ không còn nhận đặt lịch.', $atTen['message']);
        }
    }

    public function test_buffers_include_reservations_on_both_adjacent_days_and_midnight_remains_invalid(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->update(['open_time' => '00:00:00', 'close_time' => '23:59:59']);
        $f['staff']->hours()->update(['start_time' => '00:00', 'end_time' => '23:59:59']);
        $f['service']->update(['duration_minutes' => 30]);
        $day = CarbonImmutable::parse($data['date']);
        $manager = app(BookingManager::class);
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['date' => $day->subDay()->toDateString(), 'time' => '23:00']));
        $manager->create($f['user'], $f['branch'], $this->bookingData($f, ['date' => $day->addDay()->toDateString(), 'time' => '00:00']));
        DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->update(['default_buffer_minutes' => 60]);
        $slots = $this->actingAs($f['user'])->getJson($this->slotsUrl($f, $data))->assertOk()->json('slots');
        $this->assertCount(48, $slots);
        $this->assertMatchesIndividualPlans($f, $data, $slots);
        $byTime = collect($slots)->keyBy('time');
        $this->assertFalse($byTime['00:00']['available']);
        $this->assertTrue($byTime['00:30']['available']);
        $this->assertTrue($byTime['22:30']['available']);
        $this->assertFalse($byTime['23:00']['available']);
        $this->assertSame('Lịch hẹn không được kéo dài qua nửa đêm.', $byTime['23:30']['message']);
    }

    public function test_missing_hours_closed_weekday_and_holiday_return_no_choices(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $this->actingAs($f['user']);
        DB::table('branch_holidays')->insert(['branch_id' => $f['branch']->id, 'date' => $data['date'], 'name' => 'Closed', 'is_closed' => true]);
        $this->getJson($this->slotsUrl($f, $data))->assertOk()->assertJson(['slots' => [], 'message' => 'Salon không mở cửa vào ngày này.']);
        DB::table('branch_holidays')->where('branch_id', $f['branch']->id)->delete();
        DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->update(['is_closed' => true]);
        $this->getJson($this->slotsUrl($f, $data))->assertOk()->assertJson(['slots' => []]);
        DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->delete();
        $this->getJson($this->slotsUrl($f, $data))->assertOk()->assertJson(['slots' => []]);
    }

    public function test_booking_rechecks_busy_data_in_transaction_after_slots_and_next_request_is_fresh(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $availability = app(Availability::class);
        $this->app->instance(Availability::class, $availability);
        $url = $this->slotsUrl($f, $data);
        $slots = collect($this->actingAs($f['user'])->getJson($url)->assertOk()->json('slots'));
        $this->assertTrue($slots->firstWhere('time', '10:00')['available']);
        app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $slots = collect($this->getJson($url)->assertOk()->json('slots'));
        $this->assertFalse($slots->firstWhere('time', '10:00')['available']);
        $outerLevel = DB::transactionLevel();
        $levels = [];
        DB::listen(function (QueryExecuted $query) use (&$levels): void {
            if (str_contains($query->sql, 'booking_services') && str_contains($query->sql, 'appointment_end_time')) {
                $levels[] = $query->connection->transactionLevel();
            }
        });
        $this->post(route('bookings.store', $f['branch']), $data)->assertSessionHasErrors('time');
        $this->assertNotEmpty($levels);
        $this->assertGreaterThan($outerLevel, min($levels));
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_booking_rechecks_policy_after_slot_preview_even_with_same_availability_instance(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $this->app->instance(Availability::class, app(Availability::class));
        $slots = collect($this->actingAs($f['user'])->getJson($this->slotsUrl($f, $data))->assertOk()->json('slots'));
        $this->assertTrue($slots->firstWhere('time', '10:00')['available']);
        DB::table('branch_booking_policies')->where('branch_id', $f['branch']->id)->update(['lead_time_minutes' => 10080]);
        $this->post(route('bookings.store', $f['branch']), $data)
            ->assertSessionHasErrors(['time' => 'Ngày giờ nằm ngoài thời gian nhận đặt lịch.']);
        $this->assertDatabaseCount('bookings', 0);
    }
}
