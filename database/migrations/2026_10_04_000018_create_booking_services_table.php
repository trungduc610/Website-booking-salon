<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_services', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('booking_id')->unsigned();
            $table->integer('service_id')->unsigned();
            $table->integer('combo_id')->unsigned()->nullable();
            $table->integer('staff_id')->unsigned()->nullable();
            $table->string('service_name_snapshot', 200);
            $table->decimal('price_at_booking', 12, 2);
            $table->smallInteger('duration_minutes')->unsigned();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->enum('status', ['SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED','SKIPPED'])->default('SCHEDULED');
            $table->dateTime('item_start_at')->nullable();
            $table->dateTime('item_end_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services');
            $table->foreign('combo_id')->references('id')->on('combos')->onDelete('set null');
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('set null');
            $table->index(['booking_id'], 'booking_services_idx_booking');
            $table->index(['staff_id', 'status'], 'booking_services_idx_staff_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_services');
    }
};
