<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('customer_id')->unsigned()->nullable();
            $table->integer('branch_id')->unsigned();
            $table->string('booking_code', 30)->unique();
            $table->date('appointment_date');
            $table->time('appointment_start_time');
            $table->time('appointment_end_time');
            $table->enum('status', ['PENDING','CONFIRMED','CHECKED_IN','IN_PROGRESS','COMPLETED','CANCELLED','NO_SHOW','REJECTED','EXPIRED'])->default('PENDING');
            $table->enum('source', ['ONLINE_WEB','ONLINE_APP','WALK_IN','PHONE','STAFF_CREATED','ADMIN_CREATED'])->default('ONLINE_WEB');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->integer('voucher_id')->unsigned()->nullable();
            $table->decimal('voucher_discount_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->integer('cancelled_by')->unsigned()->nullable();
            $table->dateTime('pending_expires_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('customer_id')->references('id')->on('customer_profiles')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['customer_id'], 'bookings_idx_customer');
            $table->index(['branch_id', 'appointment_date'], 'bookings_idx_branch_date');
            $table->index(['status'], 'bookings_idx_status');
            $table->index(['status', 'pending_expires_at'], 'bookings_idx_pending_exp');
            $table->index(['branch_id', 'appointment_date', 'status', 'appointment_start_time', 'appointment_end_time'], 'bookings_idx_conflict_lookup');
            $table->foreign('voucher_id')->references('id')->on('vouchers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
