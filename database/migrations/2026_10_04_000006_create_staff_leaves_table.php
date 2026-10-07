<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_leaves', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('staff_id')->unsigned();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('reason', 500)->nullable();
            $table->enum('status', ['PENDING','APPROVED','REJECTED','CANCELLED'])->default('PENDING');
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->string('review_note', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['staff_id', 'start_at', 'end_at'], 'staff_leaves_idx_staff_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
    }
};
