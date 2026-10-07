<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('review_service_ratings', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('review_id')->unsigned();
            $table->integer('booking_service_id')->unsigned()->unique();
            $table->integer('staff_id')->unsigned()->nullable();
            $table->tinyInteger('rating')->unsigned();
            $table->text('comment')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade');
            $table->foreign('booking_service_id')->references('id')->on('booking_services');
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_service_ratings');
    }
};
