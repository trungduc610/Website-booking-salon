<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('province_id')->unsigned();
            $table->string('name', 100);
            $table->string('code', 10)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
            $table->unique([ 'province_id', 'name' ], 'uq_province_district');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
