<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['PLATFORM_ADMIN', 'Quản trị Platform', 'PLATFORM'],
            ['COMPLIANCE', 'Tuân thủ pháp lý', 'PLATFORM'],
            ['SUPPORT', 'Hỗ trợ khách hàng', 'PLATFORM'],
            ['MARKETING', 'Marketing', 'PLATFORM'],
            ['FINANCE', 'Tài chính', 'PLATFORM'],
            ['BUSINESS_OWNER', 'Chủ salon', 'TENANT'],
            ['BRANCH_MANAGER', 'Quản lý chi nhánh', 'BRANCH'],
            ['RECEPTIONIST', 'Lễ tân', 'BRANCH'],
            ['STAFF', 'Nhân viên', 'BRANCH'],
            ['CUSTOMER', 'Khách hàng', 'CUSTOMER'],
            ['GUEST', 'Khách vãng lai', 'CUSTOMER'],
        ];

        foreach ($roles as [$code, $name, $level]) {
            Role::updateOrCreate(['code' => $code], ['name' => $name, 'level' => $level]);
        }

        if (app()->environment('local') && ! \App\Models\Business::withTrashed()->exists()) {
            $this->call(DemoSalonSeeder::class);
        }
    }
}
