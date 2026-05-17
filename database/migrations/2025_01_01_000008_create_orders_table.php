<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            $t->string('status', 24)->default('draft');
            // draft|confirmed|paid|fulfilled|cancelled|refunded

            $t->string('customer_name')->nullable();
            $t->string('customer_phone', 32)->nullable();
            $t->string('city', 64)->nullable();
            $t->string('address')->nullable();
            $t->string('preferred_branch', 64)->nullable();
            $t->string('delivery_method', 24)->nullable();  // pickup|courier|cod
            $t->string('payment_method', 24)->nullable();   // branch|card|cod
            $t->text('notes')->nullable();

            $t->json('items_json');                         // [{sku, qty, price}]
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('delivery_fee', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->string('currency', 8)->default('GEL');

            $t->string('payment_status', 24)->default('pending');
            $t->string('payment_link')->nullable();
            $t->string('payment_provider_ref', 191)->nullable();

            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('fulfilled_at')->nullable();

            $t->timestamps();

            $t->index(['status', 'created_at']);
            $t->index(['payment_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
