<?php

namespace App\Services\Products;

use App\Models\Product;

/**
 * Read-side of the product catalog. Mirrors gadget.ge's inventory
 * locally so AI tool calls don't hammer the upstream API.
 *
 * Search is intentionally simple SQL today. Plug in pgvector or
 * Pinecone later without changing the public signatures.
 */
class ProductCatalog
{
    /**
     * @return Product[]
     */
    public function search(
        string $query,
        ?string $category = null,
        ?string $brand = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        bool $inStock = true,
        int $limit = 5,
    ): array {
        $q = Product::query()->where('is_active', true);

        if ($inStock) {
            $q->where('stock_total', '>', 0);
        }
        if ($category) {
            $q->where('category', $category);
        }
        if ($brand) {
            $q->where('brand', $brand);
        }
        if ($minPrice !== null) {
            $q->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $q->where('price', '<=', $maxPrice);
        }

        if (trim($query) !== '') {
            $tokens = preg_split('/\s+/', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $t) {
                $q->where(function ($qq) use ($t) {
                    $qq->where('name', 'like', "%$t%")
                        ->orWhere('description', 'like', "%$t%")
                        ->orWhere('brand', 'like', "%$t%")
                        ->orWhere('model', 'like', "%$t%")
                        ->orWhere('sku', 'like', "%$t%");
                });
            }
        }

        return $q->orderByDesc('is_promo')
            ->orderByDesc('margin_rank')
            ->orderByDesc('stock_total')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }
}
