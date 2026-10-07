<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('account_tokens', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->enum('type', ['EMAIL_VERIFICATION','PASSWORD_RESET']);
            $table->string('token_hash', 255)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index([ 'user_id', 'type' ], 'idx_user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_tokens');
    }
};
