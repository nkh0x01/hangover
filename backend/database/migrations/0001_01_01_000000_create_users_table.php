<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('ulid', 26)->unique();
            $table->enum('type', ['customer', 'driver', 'admin', 'dispatcher'])->index();

            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();

            $table->string('phone_e164', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at', 3)->nullable();

            $table->string('email', 190)->nullable();
            $table->timestamp('email_verified_at', 3)->nullable();
            $table->string('password')->nullable();

            $table->string('avatar_path', 255)->nullable();
            $table->enum('locale', ['ka', 'en', 'ru'])->default('ka');
            $table->enum('status', ['active', 'suspended', 'banned', 'deleted'])->default('active')->index();

            $table->char('referral_code', 8)->unique();
            $table->unsignedBigInteger('referred_by_user_id')->nullable();

            $table->timestamp('last_seen_at', 3)->nullable();
            $table->rememberToken();
            $table->timestamps(3);
            $table->softDeletes('deleted_at', 3);

            $table->foreign('referred_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
