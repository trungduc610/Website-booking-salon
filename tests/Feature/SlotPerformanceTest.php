<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class SlotPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    public function test_measure_slot_queries_for_twenty_and_forty_eight_choices(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $url = route('bookings.slots', $f['branch']).'?'.http_build_query($data);
        $this->actingAs($f['user']);
        $counts = [];
        foreach ([20, 48] as $choices) {
            if ($choices === 48) {
                DB::table('branch_working_hours')->where('branch_id', $f['branch']->id)->update(['open_time' => '00:00:00', 'close_time' => '23:59:59']);
                $f['staff']->hours()->update(['start_time' => '00:00:00', 'end_time' => '23:59:59']);
                $f['service']->update(['duration_minutes' => 30]);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $response = $this->getJson($url)->assertOk();
                $queries = DB::getQueryLog();
            } finally {
                DB::disableQueryLog();
            }
            $this->assertCount($choices, $response->json('slots'));
            $counts[] = count($queries);
            $this->assertLessThanOrEqual(12, count($queries));
            if (getenv('GLOWBOOK_MEASURE_SLOTS') === '1') {
                fwrite(STDOUT, PHP_EOL.'SLOT_QUERIES choices='.$choices.' queries='.count($queries).PHP_EOL);
            }
        }
        $this->assertSame($counts[0], $counts[1]);
    }
}
