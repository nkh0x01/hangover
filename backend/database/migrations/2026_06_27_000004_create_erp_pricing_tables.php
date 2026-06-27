<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gadget ERP — S1 retail/wholesale pricing.
 *
 * A price list is scoped by brand and optionally by branch and type
 * (retail/wholesale), so the 16 branches can price the same SKU differently
 * per brand. Prices are in GEL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->string('name', 120);
            $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->enum('type', ['retail', 'wholesale'])->default('retail');
            $t->char('currency', 3)->default('GEL');
            $t->boolean('is_active')->default(true);
            $t->timestamps(3);

            $t->index(['brand_id', 'branch_id', 'type']);
        });

        Schema::create('price_list_items', function (Blueprint $t): void {
            $t->bigIncrements('id');
            $t->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $t->decimal('price', 12, 2);
            $t->boolean('vat_included')->default(true);
            $t->timestamps(3);

            $t->unique(['price_list_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
