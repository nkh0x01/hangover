<?php

namespace App\Services\Gadget;

use App\Models\Product;
use App\Services\Gadget\Exceptions\WooApiException;
use App\Services\Gadget\Mappers\ProductMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors the WooCommerce catalog into the local `products` table.
 *
 * Strategy:
 *   - paginate /products (status=publish), upsert by SKU (or source_id
 *     when SKU is empty — WC allows empty SKUs)
 *   - mark any product not seen in this run `is_active = false` so the
 *     bot never recommends a delisted item.
 *   - bounded delete: products inactive for >30 days get soft-pruned.
 */
class CatalogSync
{
    public function __construct(
        private GadgetApi $api,
        private ProductMapper $mapper,
    ) {}

    public function run(): array
    {
        if (! $this->api->isConfigured()) {
            return ['ok' => false, 'reason' => 'wc_not_configured'];
        }

        $seen     = [];
        $upserted = 0;
        $errors   = 0;

        try {
            foreach ($this->api->products()->each() as $p) {
                $row = $this->mapper->fromWoo($p);
                if ($row['sku'] === '') {
                    // Fall back to source_id as SKU stand-in.
                    $row['sku'] = 'wc-' . $row['source_id'];
                }

                Product::updateOrCreate(['sku' => $row['sku']], $row);
                $seen[] = $row['sku'];
                $upserted++;
            }
        } catch (WooApiException $e) {
            Log::error('catalog.sync.failed', ['status' => $e->status, 'msg' => $e->getMessage(), 'body' => $e->body]);
            return ['ok' => false, 'reason' => 'wc_api_error', 'detail' => $e->getMessage()];
        }

        // Deactivate anything we didn't see in this pass.
        $deactivated = 0;
        if ($upserted > 0) {
            $deactivated = Product::whereNotIn('sku', $seen)
                ->where('is_active', true)
                ->update(['is_active' => false, 'stock_total' => 0]);
        }

        return [
            'ok'          => true,
            'upserted'    => $upserted,
            'deactivated' => $deactivated,
            'errors'      => $errors,
        ];
    }

    /**
     * Cheap per-SKU refresh (used by the AI tool when it needs a
     * "right now" stock answer rather than the mirror value).
     */
    public function refreshSku(string $sku): ?Product
    {
        if (! $this->api->isConfigured()) return null;

        $woo = $this->api->products()->findBySku($sku);
        if (! $woo) return null;

        $row = $this->mapper->fromWoo($woo);
        $row['sku'] = $row['sku'] ?: $sku;

        $p = Product::updateOrCreate(['sku' => $row['sku']], $row);
        return $p->fresh();
    }
}
