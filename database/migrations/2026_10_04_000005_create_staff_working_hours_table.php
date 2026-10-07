<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_working_hours', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('staff_id')->unsigned();
            $table->tinyInteger('day_of_week')->unsigned();
            $table->time('start_time');
            $table->time('end_time');
            $table->tinyInteger('is_off')->default(0);
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('cascade');
            $table->unique(['staff_id', 'day_of_week'], 'staff_working_hours_uq_staff_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_working_hours');
    }
};
