<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_attendances', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->integer('branch_id')->unsigned();
            $table->integer('staff_id')->unsigned();
            $table->date('work_date');
            $table->time('scheduled_start_time');
            $table->time('scheduled_end_time');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->enum('check_in_method', ['QR','MANUAL','ADMIN'])->nullable();
            $table->enum('check_out_method', ['QR','MANUAL','ADMIN'])->nullable();
            $table->enum('status', ['NOT_CHECKED_IN','CHECKED_IN','CHECKED_OUT','ABSENT','ON_LEAVE'])->default('NOT_CHECKED_IN');
            $table->smallInteger('late_minutes')->unsigned()->default(0);
            $table->smallInteger('early_leave_minutes')->unsigned()->default(0);
            $table->smallInteger('overtime_minutes')->unsigned()->default(0);
            $table->string('note', 500)->nullable();
            $table->integer('adjusted_by')->unsigned()->nullable();
            $table->dateTime('adjusted_at')->nullable();
            $table->string('adjustment_reason', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('cascade');
            $table->foreign('adjusted_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['staff_id', 'branch_id', 'work_date'], 'staff_attendances_uq_staff_branch_date');
            $table->index(['branch_id', 'work_date', 'status'], 'staff_attendances_idx_branch_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
