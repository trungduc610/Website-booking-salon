<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('request_token')->nullable()->unique();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users');
        });
        Schema::table('refund_requests', function (Blueprint $table): void {
            $table->uuid('request_token')->nullable()->unique();
            $table->unsignedInteger('processed_by')->nullable();
            $table->foreign('processed_by')->references('id')->on('users');
            $table->string('transaction_ref', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table): void {
            $table->dropForeign(['processed_by']);
            $table->dropUnique(['request_token']);
            $table->dropColumn(['request_token', 'processed_by', 'transaction_ref']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['recorded_by']);
            $table->dropUnique(['request_token']);
            $table->dropColumn(['request_token', 'recorded_by']);
        });
    }
};
