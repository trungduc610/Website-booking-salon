<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('booking_id')->unsigned();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['CASH','BANK_TRANSFER','MOMO','VNPAY','ZALOPAY','CREDIT_CARD']);
            $table->enum('status', ['PENDING','PAID','PARTIALLY_PAID','FAILED','REFUNDED','PARTIALLY_REFUNDED'])->default('PENDING');
            $table->string('transaction_ref', 200)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('booking_id')->references('id')->on('bookings');
            $table->index(['booking_id'], 'payments_idx_booking');
            $table->index(['status'], 'payments_idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
