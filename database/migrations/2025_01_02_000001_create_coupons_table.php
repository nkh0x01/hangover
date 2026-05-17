<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('source_id', 64)->nullable()->index();
            $t->string('code', 64)->unique();
            $t->string('discount_type', 24)->default('percent');  // percent|fixed_cart|fixed_product
            $t->decimal('amount', 12, 2)->default(0);
            $t->decimal('min_amount', 12, 2)->nullable();
            $t->decimal('max_amount', 12, 2)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->json('product_skus_json')->nullable();
            $t->json('product_categories_json')->nullable();
            $t->json('excluded_skus_json')->nullable();
            $t->boolean('individual_use')->default(false);
            $t->boolean('free_shipping')->default(false);
            $t->integer('usage_limit')->nullable();
            $t->integer('usage_count')->default(0);
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();

            $t->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
