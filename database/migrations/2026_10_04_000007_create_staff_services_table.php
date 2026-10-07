<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_services', function (Blueprint $table): void {
            $table->integer('staff_id')->unsigned();
            $table->integer('service_id')->unsigned();
            $table->dateTime('created_at')->useCurrent();
            $table->primary(['staff_id', 'service_id']);
            $table->foreign('staff_id')->references('id')->on('staff_profiles')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_services');
    }
};
