<?php

namespace App\Services\Products;

use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls products from the upstream gadget.ge catalog and upserts into
 * the local `products` table. The upstream schema is treated as a
 * loose contract — only fields we recognise are mapped.
 *
 * Run periodically: `php artisan catalog:sync`
 */
class CatalogSyncService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 30, 'http_errors' => false]);
    }

    public function sync(?string $sourceUrl = null): array
    {
        $url = $sourceUrl ?? config('catalog.source_url');
        if (! $url) {
            return ['ok' => false, 'reason' => 'no_source_url'];
        }

        try {
            $res = $this->http->get($url);
            $items = json_decode((string) $res->getBody(), true) ?: [];
        } catch (Throwable $e) {
            Log::error('catalog.sync.fetch_failed', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'reason' => 'fetch_failed'];
        }

        // Accept either ['products'=>[...]] or a bare array.
        if (isset($items['products']) && is_array($items['products'])) {
            $items = $items['products'];
        }

        $upserted = 0;
        $skipped  = 0;

        foreach ($items as $row) {
            $sku = (string) ($row['sku'] ?? $row['id'] ?? '');
            if ($sku === '') { $skipped++; continue; }

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'source_id'           => (string) ($row['id'] ?? $sku),
                    'name'                => (string) ($row['name'] ?? $row['title'] ?? $sku),
                    'brand'               => $row['brand'] ?? null,
                    'model'               => $row['model'] ?? null,
                    'category'            => (string) ($row['category'] ?? 'misc'),
                    'subcategory'         => $row['subcategory'] ?? null,
                    'description'         => $row['description'] ?? null,
                    'price'               => (float) ($row['price'] ?? 0),
                    'price_promo'         => isset($row['price_promo']) ? (float) $row['price_promo'] : null,
                    'currency'            => $row['currency'] ?? 'GEL',
                    'stock_total'         => (int) ($row['stock'] ?? array_sum((array) ($row['stock_by_branch'] ?? []))),
                    'stock_by_branch_json'=> $row['stock_by_branch'] ?? null,
                    'attributes_json'     => $row['attributes'] ?? null,
                    'compatibility_json'  => $row['compatibility'] ?? null,
                    'images_json'         => $row['images'] ?? (isset($row['image']) ? [$row['image']] : null),
                    'url'                 => $row['url'] ?? null,
                    'is_active'           => (bool) ($row['active'] ?? true),
                    'is_promo'            => (bool) ($row['promo'] ?? false),
                    'synced_at'           => now(),
                ],
            );
            $upserted++;
        }

        return ['ok' => true, 'upserted' => $upserted, 'skipped' => $skipped];
    }
}
