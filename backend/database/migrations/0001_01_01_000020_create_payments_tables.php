<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('provider', ['stripe', 'bog', 'tbc_pay', 'apple_pay', 'google_pay']);
            $t->string('provider_method_id', 255);
            $t->string('brand', 20)->nullable();
            $t->char('last4', 4)->nullable();
            $t->unsignedTinyInteger('exp_month')->nullable();
            $t->smallInteger('exp_year')->nullable();
            $t->boolean('is_default')->default(false);
            $t->enum('status', ['active', 'expired', 'removed'])->default('active');
            $t->timestamps(3);

            $t->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $t->enum('provider', ['stripe', 'bog', 'tbc_pay', 'apple_pay', 'google_pay', 'cash', 'wallet']);
            $t->string('provider_intent_id', 190)->nullable()->index();
            $t->enum('method', ['cash', 'card', 'wallet', 'apple_pay', 'google_pay']);
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded', 'partially_refunded', 'cancelled'])->default('pending');
            $t->string('failure_code', 60)->nullable();
            $t->timestamp('captured_at', 3)->nullable();
            $t->json('raw_response')->nullable();
            $t->timestamps(3);

            $t->index(['ride_id']);
            $t->index(['customer_id', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->string('reason', 120);
            $t->foreignId('initiated_by_user_id')->constrained('users')->cascadeOnDelete();
            $t->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $t->string('provider_refund_id', 190)->nullable();
            $t->timestamps(3);
        });

        Schema::create('payouts', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->date('period_start');
            $t->date('period_end');
            $t->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
            $t->enum('provider', ['stripe_connect', 'manual_bank'])->default('manual_bank');
            $t->string('provider_payout_id', 190)->nullable();
            $t->timestamp('processed_at', 3)->nullable();
            $t->timestamps(3);

            $t->index(['driver_id', 'status']);
        });

        Schema::table('rides', function (Blueprint $t): void {
            $t->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $t): void {
            $t->foreign('ride_id')->references('id')->on('rides')->nullOnDelete();
            $t->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $t->foreign('payout_id')->references('id')->on('payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t): void {
            $t->dropForeign(['payout_id']);
            $t->dropForeign(['payment_id']);
            $t->dropForeign(['ride_id']);
        });
        Schema::table('rides', function (Blueprint $t): void {
            $t->dropForeign(['payment_id']);
        });

        Schema::dropIfExists('payouts');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
    }
};
