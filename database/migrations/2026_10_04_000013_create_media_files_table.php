<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('url', 500);
            $table->string('original_name', 300)->nullable();
            $table->string('safe_name', 300)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->unsigned()->nullable();
            $table->integer('uploaded_by')->unsigned()->nullable();
            $table->integer('business_id')->unsigned()->nullable();
            $table->integer('branch_id')->unsigned()->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->integer('entity_id')->unsigned()->nullable();
            $table->enum('visibility', ['PUBLIC','PRIVATE'])->default('PUBLIC');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['entity_type', 'entity_id'], 'media_files_idx_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
