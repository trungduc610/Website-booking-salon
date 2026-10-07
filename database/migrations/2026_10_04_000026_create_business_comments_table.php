<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('business_comments', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('business_id')->unsigned();
            $table->integer('review_id')->unsigned()->nullable()->unique();
            $table->integer('customer_id')->unsigned();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->text('content');
            $table->enum('status', ['VISIBLE','HIDDEN'])->default('VISIBLE');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('review_id')->references('id')->on('reviews')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customer_profiles');
            $table->foreign('parent_id')->references('id')->on('business_comments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_comments');
    }
};
