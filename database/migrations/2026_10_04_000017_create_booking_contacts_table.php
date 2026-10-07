<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('booking_contacts', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('booking_id')->unsigned()->unique();
            $table->string('full_name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_contacts');
    }
};
