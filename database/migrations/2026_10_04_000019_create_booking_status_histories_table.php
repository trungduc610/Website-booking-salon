<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_status_histories', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('booking_id')->unsigned();
            $table->string('status', 30);
            $table->integer('changed_by')->unsigned()->nullable();
            $table->string('note', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['booking_id'], 'booking_status_histories_idx_booking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');
    }
};
