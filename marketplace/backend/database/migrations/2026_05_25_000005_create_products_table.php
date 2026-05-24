<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('title_ka');
            $table->string('title_en')->nullable();
            $table->longText('description_ka');
            $table->longText('description_en')->nullable();
            $table->json('materials')->nullable();
            $table->json('dimensions')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('price_gel', 10, 2);
            $table->decimal('compare_at_price_gel', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_made_to_order')->default(false);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('production_type', 32)->default('local_production');
            $table->string('country_of_production', 2)->default('GE');
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['category_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index('production_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
