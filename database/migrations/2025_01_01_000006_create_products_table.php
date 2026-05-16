<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 64)->unique();
            $t->string('source_id', 64)->nullable()->index();  // id in upstream catalog
            $t->string('name');
            $t->string('brand', 64)->nullable()->index();
            $t->string('model', 128)->nullable();
            $t->string('category', 64)->index();
            $t->string('subcategory', 64)->nullable();
            $t->text('description')->nullable();
            $t->decimal('price', 12, 2)->default(0);
            $t->decimal('price_promo', 12, 2)->nullable();
            $t->string('currency', 8)->default('GEL');
            $t->integer('stock_total')->default(0);
            $t->json('stock_by_branch_json')->nullable();
            $t->json('attributes_json')->nullable();
            $t->json('compatibility_json')->nullable();
            $t->json('images_json')->nullable();              // array of urls
            $t->string('url')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_promo')->default(false);
            $t->float('margin_rank')->default(0);              // pre-computed for ranking
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();

            $t->index(['category', 'is_active']);
            $t->index(['brand', 'category']);
            $t->index('is_promo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
