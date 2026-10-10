<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSalonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\CreatesBookingFixture;
use Tests\TestCase;

class DemoSalonTest extends TestCase
{
    use CreatesBookingFixture;
    use RefreshDatabase;

    public function test_fresh_local_install_seeds_a_catalog_without_creating_a_login(): void
    {
        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('branches', 3);
        $this->assertDatabaseCount('services', 12);
        $this->assertDatabaseCount('staff_profiles', 6);
        $this->assertFalse(User::sole()->is_active);
        $this->assertDatabaseCount('user_roles', 0);
        $this->get(route('salons.index'))->assertOk()
            ->assertSee('3 salon đang nhận lịch')->assertSee('GlowBook · Thảo Điền (mẫu)');
    }

    public function test_every_demo_branch_has_selectable_services_staff_slots_and_can_be_booked(): void
    {
        $this->seed(DemoSalonSeeder::class);
        $customer = User::create([
            'full_name' => 'Demo customer', 'email' => 'customer@example.test',
            'password_hash' => Hash::make('Customer123!'),
        ]);
        $customer->customerProfile()->create();
        $this->actingAs($customer);

        foreach (Branch::all() as $branch) {
            $this->get(route('bookings.create', $branch))->assertOk()
                ->assertSee('Cắt &amp; tạo kiểu', false)->assertSee('Massage thư giãn')->assertSee('Linh Nguyễn');
            $data = [
                'date' => now($branch->timezone)->addDays(3)->toDateString(), 'time' => '10:00',
                'service_ids' => $branch->services()->pluck('id')->all(),
                'request_token' => (string) Str::uuid(),
            ];
            $this->getJson(route('bookings.slots', [$branch, ...$data]))->assertOk()
                ->assertJsonFragment(['time' => '10:00', 'available' => true, 'message' => 'Còn chỗ']);
            $this->post(route('bookings.store', $branch), $data)->assertRedirect();
            $booking = Booking::where('branch_id', $branch->id)->sole();
            $this->assertSame('CONFIRMED', $booking->status);
            $this->assertCount(4, $booking->items);
        }
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_repeated_seeding_preserves_existing_edits_and_hours(): void
    {
        $this->seed(DemoSalonSeeder::class);
        $branch = Branch::firstOrFail();
        $branch->update(['name' => 'Tên đã chỉnh sửa']);
        $branch->services()->firstOrFail()->update(['price' => '999000.00']);
        DB::table('branch_working_hours')->where('branch_id', $branch->id)->update(['open_time' => '09:00:00']);
        $this->seed(DemoSalonSeeder::class);
        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseCount('branches', 3);
        $this->assertDatabaseCount('services', 12);
        $this->assertSame('Tên đã chỉnh sửa', $branch->fresh()->name);
        $this->assertSame('999000.00', $branch->services()->firstOrFail()->price);
        $this->assertDatabaseHas('branch_working_hours', ['branch_id' => $branch->id, 'open_time' => '09:00:00']);
    }

    public function test_default_seeding_does_not_add_samples_to_an_existing_business(): void
    {
        $this->bookingFixture();
        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('businesses', 1);
        $this->assertDatabaseMissing('businesses', ['slug' => 'glowbook-demo']);
    }

    public function test_production_seeding_only_creates_roles_and_rejects_demo_data(): void
    {
        $this->app['env'] = 'production';
        app(DatabaseSeeder::class)->run();
        $this->assertDatabaseCount('roles', 11);
        $this->assertDatabaseCount('businesses', 0);
        $this->expectException(\RuntimeException::class);
        app(DemoSalonSeeder::class)->run();
    }

    public function test_empty_catalog_and_unmatched_search_have_distinct_messages(): void
    {
        $this->get(route('salons.index'))->assertOk()->assertSee('Chưa có salon đang nhận lịch');
        $this->seed(DemoSalonSeeder::class);
        $this->get(route('salons.index', ['q' => 'Không có tên này']))->assertOk()
            ->assertSee('Chưa tìm thấy không gian phù hợp')->assertDontSee('Chưa có salon đang nhận lịch');
        Business::sole()->update(['status' => 'PENDING']);
        $this->get(route('salons.index'))->assertOk()->assertSee('Chưa có salon đang nhận lịch');
    }
}
