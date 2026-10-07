<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('booking_id')->unsigned()->unique();
            $table->integer('customer_id')->unsigned();
            $table->integer('branch_id')->unsigned();
            $table->tinyInteger('overall_rating')->unsigned();
            $table->text('comment')->nullable();
            $table->tinyInteger('is_anonymous')->default(0);
            $table->enum('status', ['PENDING','PUBLISHED','HIDDEN','REJECTED'])->default('PENDING');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('booking_id')->references('id')->on('bookings');
            $table->foreign('customer_id')->references('id')->on('customer_profiles');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->index(['branch_id', 'status'], 'reviews_idx_branch_status');
            $table->index(['customer_id'], 'reviews_idx_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
