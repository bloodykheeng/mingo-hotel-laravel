<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('email')->nullable()->unique()->index();
            $table->text('address')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('allow_notifications')->default(true);
            $table->string('status')->nullable()->default("deactive")->index();
            $table->timestamp('lastlogin')->nullable()->useCurrent();
            $table->text('photo_url')->nullable();
            $table->text('device_token')->nullable();
            $table->text('web_app_firebase_token')->nullable();
            $table->string('password');

            // New fields
            $table->boolean('agree')->default(false);
            $table->string('phone')->nullable()->unique();
            $table->string('gender')->nullable()->index();
            $table->string('nationality')->nullable()->index();
            $table->integer('age')->nullable();
            $table->datetime('date_of_birth')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            // Add foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
