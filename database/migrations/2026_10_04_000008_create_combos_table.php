<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->integer('branch_id')->unsigned();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('combo_price', 12, 2);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();
            $table->integer('max_usage')->unsigned()->nullable();
            $table->integer('used_count')->unsigned()->default(0);
            $table->enum('status', ['ACTIVE','INACTIVE','PAUSED','EXPIRED'])->default('ACTIVE');
            $table->string('cover_image', 300)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->index(['branch_id', 'status'], 'combos_idx_branch_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};
