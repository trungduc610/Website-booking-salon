<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

trait CreatesBookingFixture
{
    private function bookingFixture(): array
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::create(['full_name' => 'Test owner', 'email' => 'booking@example.test', 'password_hash' => Hash::make('Secret123!')]);
        $user->customerProfile()->create();
        $ownerId = DB::table('business_owner_profiles')->insertGetId(['user_id' => $user->id]);
        $business = Business::create(['owner_id' => $ownerId, 'name' => 'Test salon', 'slug' => 'test-salon', 'status' => 'ACTIVE']);
        $branch = Branch::create(['business_id' => $business->id, 'name' => 'Test branch', 'status' => 'ACTIVE', 'operational_status' => 'ACTIVE', 'timezone' => 'Asia/Ho_Chi_Minh', 'pending_hold_minutes' => 30]);
        $category = ServiceCategory::create(['business_id' => $business->id, 'name' => 'Hair', 'slug' => 'hair']);
        $service = new Service(['name' => 'Haircut', 'price' => '123456.78', 'duration_minutes' => 60, 'category_id' => $category->id, 'status' => 'ACTIVE', 'bookable' => true]);
        $service->business_id = $business->id;
        $branch->services()->save($service);
        $staff = $branch->staff()->create(['full_name' => 'Stylist', 'status' => 'ACTIVE', 'is_bookable' => true]);
        $staff->services()->attach($service->id);
        for ($day = 0; $day < 7; $day++) {
            DB::table('branch_working_hours')->insert(['branch_id' => $branch->id, 'day_of_week' => $day, 'open_time' => '08:00:00', 'close_time' => '18:00:00']);
            $staff->hours()->create(['day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'is_off' => false]);
        }
        DB::table('branch_booking_policies')->insert(['branch_id' => $branch->id, 'lead_time_minutes' => 0, 'cancellation_hours' => 0]);
        $user->roles()->attach(Role::where('code', 'BUSINESS_OWNER')->sole()->id, ['business_id' => $business->id]);
        return compact('user', 'business', 'branch', 'service', 'staff', 'category');
    }

    private function bookingData(array $fixture, array $extra = []): array
    {
        return array_merge(['date' => now('Asia/Ho_Chi_Minh')->addDays(3)->toDateString(), 'time' => '10:00',
            'service_ids' => [$fixture['service']->id], 'request_token' => (string) \Illuminate\Support\Str::uuid()], $extra);
    }
}
