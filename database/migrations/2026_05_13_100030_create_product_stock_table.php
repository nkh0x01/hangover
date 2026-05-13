<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_stock is the SINGLE source of truth for "how much of product X
     * is at location Y right now." Only InventoryService writes to this table;
     * every change must be paired with an inventory_movements row in the same
     * transaction.
     */
    public function up(): void
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'inventory_location_id'], 'product_stock_prod_loc_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock');
    }
};
