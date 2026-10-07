<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('payment_id')->unsigned();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->enum('status', ['PENDING','APPROVED','REJECTED','PROCESSING','REFUNDED','FAILED'])->default('PENDING');
            $table->integer('requested_by')->unsigned();
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->text('review_note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['payment_id', 'status'], 'refund_requests_idx_payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
