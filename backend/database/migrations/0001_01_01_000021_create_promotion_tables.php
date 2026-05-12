<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('code', 40)->unique();
            $t->enum('kind', ['percent_off', 'fixed_off', 'free_ride', 'wallet_credit']);
            $t->decimal('value', 8, 2);
            $t->char('currency', 3)->nullable();
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedTinyInteger('max_uses_per_user')->default(1);
            $t->decimal('min_ride_amount', 8, 2)->nullable();
            $t->json('applicable_city_ids')->nullable();
            $t->dateTime('valid_from');
            $t->dateTime('valid_until');
            $t->enum('status', ['active', 'paused', 'expired'])->default('active');
            $t->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(3);
            $t->softDeletes('deleted_at', 3);

            $t->index(['status', 'valid_from', 'valid_until']);
        });

        Schema::create('promo_redemptions', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->decimal('amount_applied', 8, 2);
            $t->timestamp('redeemed_at', 3)->useCurrent();

            $t->unique(['promo_code_id', 'user_id', 'ride_id']);
            $t->index(['user_id', 'redeemed_at']);
        });

        Schema::table('rides', function (Blueprint $t): void {
            $t->foreign('promo_code_id')->references('id')->on('promo_codes')->nullOnDelete();
        });

        Schema::table('promo_redemptions', function (Blueprint $t): void {
            $t->foreign('ride_id')->references('id')->on('rides')->nullOnDelete();
        });

        Schema::table('fare_estimates', function (Blueprint $t): void {
            $t->foreign('promo_code_id')->references('id')->on('promo_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fare_estimates', function (Blueprint $t): void {
            $t->dropForeign(['promo_code_id']);
        });
        Schema::table('promo_redemptions', function (Blueprint $t): void {
            $t->dropForeign(['ride_id']);
        });
        Schema::table('rides', function (Blueprint $t): void {
            $t->dropForeign(['promo_code_id']);
        });
        Schema::dropIfExists('promo_redemptions');
        Schema::dropIfExists('promo_codes');
    }
};
