<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->char('currency', 3);
            $t->decimal('balance_cached', 12, 2)->default(0);
            $t->decimal('held_amount', 12, 2)->default(0);
            $t->timestamps(3);
        });

        Schema::create('transactions', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $t->enum('kind', [
                'topup', 'ride_charge', 'ride_payout', 'refund',
                'promo_credit', 'referral_bonus', 'withdrawal',
                'adjustment', 'hold', 'release',
            ]);
            $t->enum('direction', ['credit', 'debit']);
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->unsignedBigInteger('payment_id')->nullable();
            $t->unsignedBigInteger('payout_id')->nullable();
            $t->decimal('balance_after', 12, 2);
            $t->string('description', 255)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('occurred_at', 3)->useCurrent();
            $t->timestamps(3);

            $t->index(['wallet_id', 'occurred_at']);
            $t->index(['kind', 'occurred_at']);
            $t->index(['ride_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
