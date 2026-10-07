<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('combo_services', function (Blueprint $table): void {
            $table->integer('combo_id')->unsigned();
            $table->integer('service_id')->unsigned();
            $table->tinyInteger('quantity')->unsigned()->default(1);
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->decimal('price_snapshot', 12, 2);
            $table->smallInteger('duration_snapshot')->unsigned();
            $table->primary(['combo_id', 'service_id']);
            $table->foreign('combo_id')->references('id')->on('combos')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_services');
    }
};
