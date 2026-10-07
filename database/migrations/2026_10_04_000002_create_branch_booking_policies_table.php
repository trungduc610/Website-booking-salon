<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branch_booking_policies', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('branch_id')->unsigned()->unique();
            $table->smallInteger('lead_time_minutes')->unsigned()->default(0);
            $table->smallInteger('booking_horizon_days')->unsigned()->default(90);
            $table->smallInteger('cancellation_hours')->unsigned()->default(24);
            $table->smallInteger('reschedule_hours')->unsigned()->default(12);
            $table->tinyInteger('late_cancel_fee_pct')->unsigned()->default(50);
            $table->tinyInteger('no_show_fee_pct')->unsigned()->default(100);
            $table->tinyInteger('allow_walk_in')->default(1);
            $table->tinyInteger('allow_counter_booking')->default(1);
            $table->smallInteger('default_buffer_minutes')->unsigned()->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_booking_policies');
    }
};
