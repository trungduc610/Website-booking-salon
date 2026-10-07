<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->nullable();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->enum('discount_type', ['PERCENTAGE','FIXED_AMOUNT']);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('min_order_value', 12, 2)->default(0);
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->integer('total_quantity')->unsigned()->default(0);
            $table->integer('used_quantity')->unsigned()->default(0);
            $table->tinyInteger('max_usage_per_customer')->unsigned()->default(1);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('status', ['ACTIVE','INACTIVE','EXPIRED'])->default('ACTIVE');
            $table->tinyInteger('created_by_platform')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->index(['status'], 'vouchers_idx_status');
            $table->index(['start_date', 'end_date'], 'vouchers_idx_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
