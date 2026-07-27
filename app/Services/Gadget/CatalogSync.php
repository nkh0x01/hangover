<?php

namespace App\Services\Gadget;

use App\Models\Product;
use App\Services\Gadget\Mappers\ProductMapper;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors the gadget.ge catalog into the local `products` table.
 *
 * Source: the new gadget.ge catalog API (CatalogApiClient), Bearer token —
 * gadget.ge migrated off WooCommerce to a Laravel site.
 *
 * Strategy:
 *   - paginate /products, upsert by SKU (source_id fallback when SKU empty)
 *   - deactivate anything not touched this run (via synced_at) so the bot
 *     never recommends a delisted item.
 */
class CatalogSync
{
    public function __construct(
        private CatalogApiClient $api,
        private ProductMapper $mapper,
    ) {}

    public function run(): array
    {
        if (! $this->api->isConfigured()) {
            return ['ok' => false, 'reason' => 'catalog_api_not_configured'];
        }

        $runStart = now();
        $upserted = 0;

        try {
            foreach ($this->api->each() as $p) {
                $row = $this->mapper->fromApi($p);
                if ($row['sku'] === '') {
                    $row['sku'] = 'gid-' . $row['source_id'];
                }
                if ($row['sku'] === 'gid-') {
                    continue; // no usable identity — skip
                }

                Product::updateOrCreate(['sku' => $row['sku']], $row);
                $upserted++;
            }
        } catch (Throwable $e) {
            // Partial failure (e.g. a mid-run page error). Do NOT deactivate —
            // we may have only seen some pages.
            Log::error('catalog.sync.failed', ['msg' => $e->getMessage(), 'upserted_so_far' => $upserted]);

            return ['ok' => false, 'reason' => 'api_error', 'detail' => $e->getMessage(), 'upserted' => $upserted];
        }

        // Deactivate anything not touched this run (synced_at older than the
        // run start), avoiding a giant WHERE NOT IN over thousands of SKUs.
        $deactivated = 0;
        if ($upserted > 0) {
            $deactivated = Product::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<', $runStart))
                ->update(['is_active' => false, 'stock_total' => 0]);
        }

        return ['ok' => true, 'upserted' => $upserted, 'deactivated' => $deactivated];
    }

    /**
     * Cheap per-SKU refresh (used by the AI tool for a "right now" stock
     * answer rather than the mirror value).
     */
    public function refreshSku(string $sku): ?Product
    {
        if (! $this->api->isConfigured()) {
            return null;
        }

        $p = $this->api->product($sku);
        if (! $p) {
            return null;
        }

        $row = $this->mapper->fromApi($p);
        $row['sku'] = $row['sku'] ?: $sku;

        return Product::updateOrCreate(['sku' => $row['sku']], $row)->fresh();
    }
}
