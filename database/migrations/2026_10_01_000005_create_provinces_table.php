<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name', 100)->unique();
            $table->string('code', 10)->nullable()->unique();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
