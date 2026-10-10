<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSalonSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Dữ liệu salon mẫu chỉ dành cho môi trường local hoặc testing.');
        }

        DB::transaction(function (): void {
            // Keep repeated seeding harmless, including after edits or soft deletion.
            if (Business::withTrashed()->where('slug', 'glowbook-demo')->exists()) {
                return;
            }

            $owner = new User([
                'full_name' => 'Chủ salon mẫu GlowBook',
                'email' => 'salon-demo@glowbook.example.test',
                'password_hash' => Hash::make(Str::random(64)),
            ]);
            $owner->is_active = false;
            $owner->save();
            $ownerId = DB::table('business_owner_profiles')->insertGetId(['user_id' => $owner->id]);
            $business = Business::create([
                'owner_id' => $ownerId,
                'name' => 'GlowBook Studio (mẫu)',
                'slug' => 'glowbook-demo',
                'description' => 'Dữ liệu minh họa để thử đặt lịch trên máy local, không phải salon thực tế.',
                'status' => 'ACTIVE',
            ]);

            $hair = ServiceCategory::create(['business_id' => $business->id, 'name' => 'Chăm sóc tóc', 'slug' => 'cham-soc-toc']);
            $spa = ServiceCategory::create(['business_id' => $business->id, 'name' => 'Spa & thư giãn', 'slug' => 'spa-thu-gian']);
            $treatments = [
                [$hair->id, 'Cắt & tạo kiểu', 'Tư vấn kiểu tóc và tạo kiểu theo sở thích.', '250000.00', 60],
                [$hair->id, 'Gội đầu dưỡng sinh', 'Làm sạch tóc kết hợp massage da đầu nhẹ nhàng.', '180000.00', 45],
                [$spa->id, 'Chăm sóc da cơ bản', 'Làm sạch, dưỡng ẩm và chăm sóc da.', '350000.00', 60],
                [$spa->id, 'Massage thư giãn', 'Thư giãn cơ thể sau một ngày bận rộn.', '400000.00', 90],
            ];

            foreach (['Thảo Điền', 'Quận 3', 'Phú Nhuận'] as $area) {
                $branch = Branch::create([
                    'business_id' => $business->id,
                    'name' => 'GlowBook · '.$area.' (mẫu)',
                    'address_line' => 'Khu vực '.$area.', TP. Hồ Chí Minh (địa chỉ minh họa)',
                    'description' => 'Chi nhánh mẫu phục vụ kiểm thử đặt lịch.',
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'status' => 'ACTIVE',
                    'operational_status' => 'ACTIVE',
                    'review_status' => 'APPROVED',
                    'published_at' => now(),
                    'booking_confirmation_mode' => 'AUTO_CONFIRMATION',
                    'staff_assignment_mode' => 'AUTO_ASSIGN_IF_ANY_STAFF',
                ]);
                $serviceIds = [];
                foreach ($treatments as [$categoryId, $name, $description, $price, $duration]) {
                    $service = $branch->services()->make([
                        'category_id' => $categoryId, 'name' => $name, 'description' => $description,
                        'price' => $price, 'duration_minutes' => $duration, 'status' => 'ACTIVE', 'bookable' => true,
                    ]);
                    $service->business_id = $business->id;
                    $service->save();
                    $serviceIds[] = $service->id;
                }

                DB::table('branch_booking_policies')->insert([
                    'branch_id' => $branch->id, 'lead_time_minutes' => 60, 'booking_horizon_days' => 90,
                    'cancellation_hours' => 24, 'reschedule_hours' => 12, 'default_buffer_minutes' => 0,
                ]);
                for ($day = 0; $day < 7; $day++) {
                    DB::table('branch_working_hours')->insert([
                        'branch_id' => $branch->id, 'day_of_week' => $day,
                        'open_time' => '08:00:00', 'close_time' => '20:00:00', 'is_closed' => false,
                    ]);
                }

                foreach (['Linh Nguyễn', 'Minh Anh'] as $name) {
                    $staff = $branch->staff()->create([
                        'full_name' => $name, 'position' => 'Chuyên viên chăm sóc (mẫu)',
                        'bio' => 'Hồ sơ nhân viên mẫu để thử chọn chuyên viên và khung giờ.',
                        'status' => 'ACTIVE', 'is_bookable' => true,
                    ]);
                    $staff->services()->attach($serviceIds);
                    for ($day = 0; $day < 7; $day++) {
                        $staff->hours()->create([
                            'day_of_week' => $day, 'start_time' => '08:00:00',
                            'end_time' => '20:00:00', 'is_off' => false,
                        ]);
                    }
                }
            }
        });
    }
}
