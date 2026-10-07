<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('name', 200);
            $table->string('slug', 200);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('service_categories')->nullOnDelete();
            $table->unique(['business_id', 'slug'], 'uq_biz_slug');
        });
        Schema::create('services', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('branch_id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('category_id');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('bookable')->default(true);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->string('cover_image', 300)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('service_categories');
            $table->index(['branch_id', 'bookable', 'status', 'deleted_at'], 'idx_branch_bookable');
            $table->index('price', 'idx_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
