<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branch_working_hours', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('branch_id')->unsigned();
            $table->tinyInteger('day_of_week')->unsigned();
            $table->time('open_time');
            $table->time('close_time');
            $table->tinyInteger('is_closed')->default(0);
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->unique(['branch_id', 'day_of_week'], 'branch_working_hours_uq_branch_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_working_hours');
    }
};
