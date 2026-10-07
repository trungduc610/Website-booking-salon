<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('type', 100);
            $table->enum('severity', ['INFO','WARNING','ERROR','SUCCESS'])->default('INFO');
            $table->string('title', 300);
            $table->text('body')->nullable();
            $table->tinyInteger('is_read')->default(0);
            $table->dateTime('read_at')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->integer('related_booking_id')->unsigned()->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('related_booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->index(['user_id', 'is_read'], 'notifications_idx_user_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
