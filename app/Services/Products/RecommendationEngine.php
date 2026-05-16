<?php

namespace App\Services\Products;

use App\Models\Customer;
use App\Models\Product;

class RecommendationEngine
{
    public function __construct(private ProductCatalog $catalog) {}

    /**
     * Suggest products for a customer based on their memory + an
     * optional free-text intent.
     *
     * @return Product[]
     */
    public function suggest(Customer $customer, string $intent = '', int $limit = 3): array
    {
        $memory = $customer->profile_json ?? [];
        $ecosystem = $memory['ecosystem'] ?? null;
        $budget = $memory['budget_range'] ?? null;

        [$minPrice, $maxPrice] = $this->parseBudget($budget);

        $brand = match ($ecosystem) {
            'apple' => 'Apple',
            'samsung' => 'Samsung',
            default => null,
        };

        return $this->catalog->search(
            query: $intent,
            brand: $brand,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            inStock: true,
            limit: $limit,
        );
    }

    /**
     * Same-category alternatives near a SKU's price band.
     *
     * @return Product[]
     */
    public function alternativesFor(string $sku, int $limit = 3): array
    {
        $product = $this->catalog->findBySku($sku);
        if (! $product) {
            return [];
        }
        $price = $product->effectivePrice();
        $band = max(50, $price * 0.25);

        return Product::query()
            ->where('is_active', true)
            ->where('stock_total', '>', 0)
            ->where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->whereBetween('price', [$price - $band, $price + $band])
            ->orderByDesc('margin_rank')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function parseBudget(?string $budget): array
    {
        if (! $budget) {
            return [null, null];
        }

        if (preg_match('/(\d+)\s*[-–to]+\s*(\d+)/', $budget, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }
        if (preg_match('/<\s*(\d+)/', $budget, $m)) {
            return [null, (float) $m[1]];
        }
        if (preg_match('/>\s*(\d+)/', $budget, $m)) {
            return [(float) $m[1], null];
        }

        return [null, null];
    }
}
