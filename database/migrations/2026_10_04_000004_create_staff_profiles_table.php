<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable()->unique();
            $table->integer('branch_id')->unsigned();
            $table->string('full_name', 150);
            $table->string('position', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('employee_code', 50)->nullable();
            $table->tinyInteger('experience_years')->unsigned()->nullable();
            $table->tinyInteger('public_visible')->default(1);
            $table->tinyInteger('is_bookable')->default(0);
            $table->enum('status', ['PROFILE_ONLY','INVITED','ACTIVE','LOCKED','INACTIVE','ON_LEAVE'])->default('ACTIVE');
            $table->date('hired_at')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('avatar', 300)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->unique(['branch_id', 'employee_code'], 'staff_profiles_uq_branch_code');
            $table->index(['branch_id', 'status', 'is_bookable'], 'staff_profiles_idx_branch_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
