<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('payment_method', 32)->default('cod');
            $table->string('payment_status', 16)->default('unpaid');
            $table->decimal('subtotal_gel', 12, 2);
            $table->decimal('shipping_gel', 10, 2)->default(0);
            $table->decimal('total_gel', 12, 2);
            $table->string('shipping_name');
            $table->string('shipping_phone', 32);
            $table->string('shipping_region', 64);
            $table->string('shipping_city');
            $table->string('shipping_address');
            $table->text('shipping_notes')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('placed_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title_snapshot');
            $table->string('image_snapshot')->nullable();
            $table->decimal('unit_price_gel', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total_gel', 12, 2);
            $table->timestamps();

            $table->index('order_id');
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
