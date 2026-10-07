<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branch_holidays', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('branch_id')->unsigned();
            $table->date('date');
            $table->string('name', 200);
            $table->tinyInteger('is_closed')->default(1);
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->unique(['branch_id', 'date'], 'branch_holidays_uq_branch_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_holidays');
    }
};
