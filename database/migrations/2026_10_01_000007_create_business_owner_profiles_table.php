<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('business_owner_profiles', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->unique();
            $table->string('company_name', 200)->nullable();
            $table->string('tax_code', 50)->nullable()->unique();
            $table->string('identity_card_number', 50)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_owner_profiles');
    }
};
