<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('owner_id')->unsigned();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('contact_email', 191)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('address_line', 300)->nullable();
            $table->string('logo', 300)->nullable();
            $table->enum('status', ['DRAFT','PENDING','PENDING_REVIEW','NEED_MORE_INFO','APPROVED','ACTIVE','SUSPENDED','REJECTED'])->default('PENDING');
            $table->text('review_note')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->tinyInteger('onboarding_step')->unsigned()->default(1);
            $table->dateTime('booking_restricted_at')->nullable();
            $table->string('booking_restriction_reason', 500)->nullable();
            $table->decimal('trust_score', 5, 2)->default(100.00);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('owner_id')->references('id')->on('business_owner_profiles');
            $table->index([ 'status', 'deleted_at' ], 'idx_status_deleted');
            $table->index([ 'owner_id' ], 'idx_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
