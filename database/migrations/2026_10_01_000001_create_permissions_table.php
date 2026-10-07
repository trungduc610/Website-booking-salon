<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->string('resource', 100);
            $table->string('action', 100);
            $table->enum('scope', ['PLATFORM','TENANT','BRANCH','SELF','PUBLIC'])->default('SELF');
            $table->dateTime('created_at')->useCurrent();
            $table->index([ 'resource', 'action' ], 'idx_resource_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
