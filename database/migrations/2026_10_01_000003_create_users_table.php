<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email', 191)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash', 255);
            $table->string('full_name', 150);
            $table->string('address', 300)->nullable();
            $table->string('avatar', 300)->nullable();
            $table->enum('gender', ['MALE','FEMALE','OTHER'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->tinyInteger('is_email_verified')->default(0);
            $table->tinyInteger('is_phone_verified')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->dateTime('last_login_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->index([ 'is_active' ], 'idx_active');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
