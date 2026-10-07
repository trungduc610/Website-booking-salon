<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('role_id')->unsigned();
            $table->integer('business_id')->unsigned()->nullable();
            $table->integer('branch_id')->unsigned()->nullable();
            $table->integer('granted_by')->unsigned()->nullable();
            $table->dateTime('granted_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            // Generated keys close MySQL's nullable-UNIQUE loophole without changing legacy scope columns.
            $table->unsignedInteger('scope_business_key')->storedAs('COALESCE(business_id, 0)');
            $table->unsignedInteger('scope_branch_key')->storedAs('COALESCE(branch_id, 0)');
            $table->unique(['user_id', 'role_id', 'scope_business_key', 'scope_branch_key'], 'uq_user_role_scope');
            $table->foreign('business_id')->references('id')->on('businesses');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'expires_at'], 'idx_user_role_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
