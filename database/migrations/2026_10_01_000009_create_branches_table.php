<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->string('name', 200);
            $table->string('public_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('address_line', 300)->nullable();
            $table->integer('district_id')->unsigned()->nullable();
            $table->string('ward', 100)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('timezone', 50)->default('Asia/Ho_Chi_Minh');
            $table->enum('service_mode', ['AT_LOCATION','MOBILE','BOTH'])->default('AT_LOCATION');
            $table->enum('booking_confirmation_mode', ['MANUAL_CONFIRMATION','AUTO_CONFIRMATION'])->default('MANUAL_CONFIRMATION');
            $table->enum('staff_assignment_mode', ['CUSTOMER_SELECTS_STAFF','AUTO_ASSIGN_IF_ANY_STAFF','MANUAL_ASSIGN_BY_RECEPTIONIST'])->default('AUTO_ASSIGN_IF_ANY_STAFF');
            $table->smallInteger('pending_hold_minutes')->unsigned()->default(30);
            $table->enum('status', ['PENDING','ACTIVE','INACTIVE'])->default('PENDING');
            $table->enum('review_status', ['DRAFT','SUBMITTED','PENDING_REVIEW','NEED_MORE_INFO','APPROVED','REJECTED'])->default('DRAFT');
            $table->enum('operational_status', ['INACTIVE','READY_TO_PUBLISH','ACTIVE','PAUSED','SUSPENDED','CLOSED','ARCHIVED'])->default('INACTIVE');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('set null');
            $table->index([ 'business_id' ], 'idx_business');
            $table->index([ 'status' ], 'idx_status');
            $table->index([ 'operational_status' ], 'idx_op_status');
            $table->index([ 'latitude', 'longitude' ], 'idx_geo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
