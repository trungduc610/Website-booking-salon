<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\BookingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class DesignExperienceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBookingFixture;

    public function test_branch_search_and_empty_result_are_server_rendered(): void
    {
        $f = $this->bookingFixture();
        $this->get('/')->assertRedirect('/salons');
        $this->get(route('salons.index', ['q' => 'Test branch']))->assertOk()->assertSee('Test branch');
        $this->get(route('salons.index', ['q' => 'No matching branch']))->assertOk()->assertDontSee('Test branch')->assertSee('Chưa tìm thấy');
        $this->get(route('salons.index', ['q' => ['bad']]))->assertSessionHasErrors('q');
    }

    public function test_slots_reflect_holds_and_expiration_and_enforce_authentication(): void
    {
        $f = $this->bookingFixture();
        $data = $this->bookingData($f);
        $url = route('bookings.slots', $f['branch']).'?'.http_build_query($data);
        $this->getJson($url)->assertUnauthorized();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $data);
        $slots = collect($this->actingAs($f['user'])->getJson($url)->assertOk()->json('slots'));
        $this->assertFalse($slots->firstWhere('time', '10:00')['available']);
        $this->assertTrue($slots->firstWhere('time', '11:00')['available']);
        $booking->update(['pending_expires_at' => now()->subMinute()]);
        $slots = collect($this->getJson($url)->assertOk()->json('slots'));
        $this->assertTrue($slots->firstWhere('time', '10:00')['available']);
        DB::table('branch_holidays')->insert(['branch_id' => $f['branch']->id, 'date' => $data['date'], 'name' => 'Closed', 'is_closed' => true]);
        $this->getJson($url)->assertOk()->assertJson(['slots' => []]);
    }

    public function test_operational_filters_and_platform_metrics_render(): void
    {
        $f = $this->bookingFixture();
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->actingAs($f['user'])->get(route('salon.bookings', [$f['branch'], 'status' => 'PENDING']))->assertOk()->assertSee($booking->booking_code);
        $this->get(route('salon.bookings', [$f['branch'], 'status' => 'COMPLETED']))->assertOk()->assertDontSee($booking->booking_code);
        $this->get(route('admin.dashboard'))->assertForbidden();
        $f['user']->roles()->attach(Role::where('code', 'PLATFORM_ADMIN')->sole()->id);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Chi nhánh hoạt động');
    }

    public function test_redesigned_screens_render_and_export_optional_browser_fixtures(): void
    {
        $f = $this->bookingFixture();
        $f['user']->update(['full_name' => 'Minh Anh']);
        $f['business']->update(['name' => 'The Botanical House']);
        $f['branch']->update(['name' => 'Botanical · Thảo Điền', 'address_line' => '24 Nguyễn Văn Hưởng, Thảo Điền, TP. Hồ Chí Minh']);
        $f['staff']->update(['full_name' => 'Linh Nguyễn', 'position' => 'Chuyên viên chăm sóc tóc', 'bio' => 'Chăm sóc mái tóc và giúp bạn tìm phong cách phù hợp.']);
        $f['staff']->forceFill(['public_visible' => true])->save();
        $f['service']->update(['name' => 'Cắt & tạo kiểu', 'description' => 'Một diện mạo mới, nhẹ nhàng và tự nhiên.', 'price' => '350000.00']);
        $f['category']->update(['name' => 'Chăm sóc tóc']);
        $booking = app(BookingManager::class)->create($f['user'], $f['branch'], $this->bookingData($f));
        $this->actingAs($f['user']);
        $pages = [
            'explore' => route('salons.index'),
            'booking' => route('bookings.create', $f['branch']),
            'account' => route('dashboard'),
            'appointments' => route('bookings.index'),
            'ticket' => route('bookings.show', $booking),
            'operations' => route('salon.bookings', [$f['branch'], 'date' => $booking->appointment_date]),
            'payments' => route('payments.show', $booking),
            'catalog' => route('catalog.index', $f['branch']),
            'staff' => route('staff.index', $f['branch']),
            'vouchers' => route('vouchers.index', $f['branch']),
        ];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk();
            if (getenv('GLOWBOOK_EXPORT_UI') === '1') {
                $directory = storage_path('app/ui-preview');
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                file_put_contents($directory.'/'.$name.'.html', $response->getContent());
                file_put_contents($directory.'/'.$name.'.headers.json', json_encode(['Content-Security-Policy' => $response->headers->get('Content-Security-Policy')]));
            }
        }
    }
}

