<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Fixed DDL: Schema Builder has no portable CHECK API.
        DB::statement('ALTER TABLE `bookings` ADD CONSTRAINT `chk_time_order` CHECK (`appointment_end_time` > `appointment_start_time`)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('ALTER TABLE `bookings` DROP CHECK `chk_time_order`');
    }
};
