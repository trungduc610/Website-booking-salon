<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('momo_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('payment_id')->unique();
            $table->foreign('payment_id')->references('id')->on('payments');
            $table->string('order_id', 50)->unique();
            $table->uuid('request_id')->unique();
            $table->string('partner_code', 50);
            $table->string('environment', 20);
            $table->string('order_info', 255);
            $table->text('pay_url')->nullable();
            $table->integer('result_code')->nullable();
            $table->string('transaction_id', 100)->nullable()->unique();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('momo_payment_attempts');
    }
};
