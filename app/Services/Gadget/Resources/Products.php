<?php

namespace App\Services\Gadget\Resources;

use App\Services\Gadget\WooCommerceClient;

class Products
{
    public function __construct(private WooCommerceClient $client) {}

    /** @return array<int, array> raw WC product objects */
    public function list(int $page = 1, int $perPage = 100, array $filters = []): array
    {
        return $this->client->get('products', array_merge([
            'page' => $page,
            'per_page' => $perPage,
            'status' => 'publish',
        ], $filters));
    }

    /** Iterate every published product. */
    public function each(array $filters = []): \Generator
    {
        foreach ($this->client->paginate('products', array_merge(['status' => 'publish'], $filters)) as $page) {
            foreach ($page as $product) {
                yield $product;
            }
        }
    }

    public function get(int $id): ?array
    {
        $p = $this->client->get("products/$id");

        return $p ?: null;
    }

    public function findBySku(string $sku): ?array
    {
        $items = $this->client->get('products', ['sku' => $sku, 'per_page' => 1]);

        return $items[0] ?? null;
    }

    /** Live stock check. */
    public function stockBySku(string $sku): array
    {
        $p = $this->findBySku($sku);
        if (! $p) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'sku' => $p['sku'] ?? $sku,
            'stock_status' => $p['stock_status'] ?? null,
            'stock' => $p['stock_quantity'] ?? null,
            'manage_stock' => $p['manage_stock'] ?? false,
        ];
    }

    public function variations(int $productId): array
    {
        return $this->client->get("products/$productId/variations", ['per_page' => 100]);
    }
}
