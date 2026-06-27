<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gadget ERP — S1 inventory.
 *
 * products carry a weighted-average `cost` (COGS source, recomputed on
 * goods receipt). One SKU can map to many models via variants.model_compat
 * (e.g. a case/glass that fits several phones). Stock and serials are
 * tracked per variant per branch for real-time multi-location stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('name_ka', 120);
            $t->string('name_en', 120)->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->boolean('vat_default')->default(true);
            $t->timestamps(3);

            $t->foreign('parent_id')->references('id')->on('product_categories')->nullOnDelete();
        });

        Schema::create('products', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('sku', 64)->unique();
            $t->string('name_ka', 200);
            $t->string('name_en', 200)->nullable();
            $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->boolean('vat_applicable')->default(true);
            $t->string('barcode', 64)->nullable()->index();
            $t->string('unit', 16)->default('pcs');
            $t->boolean('is_serialized')->default(false);
            // Weighted-average cost in GEL. Recomputed on every goods receipt.
            $t->decimal('cost', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);
        });

        Schema::create('product_variants', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->string('variant_sku', 64)->unique();
            $t->string('barcode', 64)->nullable()->index();
            // Models this variant fits (e.g. ["iphone-15","iphone-15-pro"]).
            $t->json('model_compat')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);
        });

        Schema::create('stock_levels', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->integer('qty')->default(0);
            $t->integer('reserved_qty')->default(0);
            $t->integer('min_qty')->default(0);
            $t->integer('max_qty')->nullable();
            $t->timestamps(3);

            $t->unique(['product_variant_id', 'branch_id']);
        });

        Schema::create('stock_movements', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->char('ulid', 26)->unique();
            $t->enum('type', ['in', 'out', 'transfer', 'adjust', 'inventory']);
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->integer('qty');
            $t->decimal('cost', 12, 2)->default(0);
            // Waybill is wired in S4 (RS.ge); kept nullable here so movement
            // mechanics exist now and the legal gate layers on top later.
            $t->unsignedBigInteger('waybill_id')->nullable();
            $t->nullableMorphs('ref');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note', 255)->nullable();
            $t->timestamp('created_at', 3)->useCurrent();

            $t->index(['product_variant_id', 'type']);
        });

        Schema::create('serial_items', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $t->string('serial_no', 120)->nullable();
            $t->string('imei', 20)->nullable();
            $t->enum('status', ['in_stock', 'in_transit', 'sold', 'rma'])->default('in_stock');
            $t->unsignedBigInteger('sale_item_id')->nullable();
            $t->timestamps(3);

            $t->index(['product_variant_id', 'status']);
            $t->index('serial_no');
            $t->index('imei');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
