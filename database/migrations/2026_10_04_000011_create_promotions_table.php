<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->nullable();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->enum('discount_type', ['PERCENTAGE','FIXED_AMOUNT']);
            $table->decimal('discount_value', 12, 2);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->enum('status', ['ACTIVE','INACTIVE','EXPIRED'])->default('ACTIVE');
            $table->tinyInteger('created_by_platform')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
