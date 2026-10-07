<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('appointment_change_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('booking_id')->unsigned();
            $table->integer('requested_by')->unsigned();
            $table->enum('request_type', ['RESCHEDULE','CHANGE_STAFF','CANCEL']);
            $table->date('proposed_date')->nullable();
            $table->time('proposed_start_time')->nullable();
            $table->time('proposed_end_time')->nullable();
            $table->integer('proposed_staff_id')->unsigned()->nullable();
            $table->string('reason', 500)->nullable();
            $table->enum('status', ['PENDING','APPROVED','REJECTED','EXPIRED'])->default('PENDING');
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('proposed_staff_id')->references('id')->on('staff_profiles')->onDelete('set null');
            $table->index(['booking_id', 'status'], 'appointment_change_requests_idx_booking_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_change_requests');
    }
};
