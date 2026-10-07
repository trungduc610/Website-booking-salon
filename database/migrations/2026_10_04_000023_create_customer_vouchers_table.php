<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_vouchers', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('voucher_id')->unsigned();
            $table->integer('customer_id')->unsigned();
            $table->enum('status', ['ACTIVE','USED','EXPIRED'])->default('ACTIVE');
            $table->dateTime('acquired_at')->useCurrent();
            $table->dateTime('used_at')->nullable();
            $table->integer('used_booking_id')->unsigned()->nullable()->unique();
            $table->dateTime('expires_at')->nullable();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customer_profiles')->onDelete('cascade');
            $table->foreign('used_booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->unique(['voucher_id', 'customer_id'], 'customer_vouchers_uq_voucher_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vouchers');
    }
};
