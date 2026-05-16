<?php

namespace App\Services\Gadget\Mappers;

use Illuminate\Support\Arr;

class ProductMapper
{
    /**
     * Translate a WooCommerce product object into the column shape
     * expected by our `products` table.
     */
    public function fromWoo(array $p): array
    {
        $map     = (array) config('gadget.product_map');
        $branches = (array) config('gadget.branches.meta_keys');

        $images = array_map(
            fn ($img) => $img['src'] ?? null,
            (array) Arr::get($p, $map['images'] ?? 'images', []),
        );
        $images = array_values(array_filter($images));

        $categories = (array) Arr::get($p, $map['categories'] ?? 'categories', []);
        $category   = $this->slugify((string) ($categories[0]['name'] ?? ($categories[0]['slug'] ?? 'misc')));

        $brand = $this->extractBrand($p, $map);

        $price        = $this->num(Arr::get($p, $map['price']       ?? 'regular_price'));
        $promoPrice   = $this->num(Arr::get($p, $map['price_promo'] ?? 'sale_price'));
        $isPromo      = (bool) Arr::get($p, $map['is_promo']        ?? 'on_sale');
        $manageStock  = (bool) Arr::get($p, $map['manage_stock']    ?? 'manage_stock');
        $stockStatus  = (string) Arr::get($p, $map['stock_status']  ?? 'stock_status', 'instock');
        $stockQty     = Arr::get($p, $map['stock']                  ?? 'stock_quantity');

        // If WC doesn't manage stock for this product, treat it as
        // "always in stock" when stock_status=instock — a common Woo
        // configuration for unlimited digital/imported goods.
        $stockTotal = match (true) {
            $manageStock && $stockQty !== null => (int) $stockQty,
            $stockStatus === 'instock'         => 9999,
            default                            => 0,
        };

        $perBranch = [];
        $meta = Arr::get($p, 'meta_data', []);
        $metaIndex = collect($meta)->keyBy('key')->map(fn ($m) => $m['value'])->all();
        foreach ($branches as $branchName => $metaKey) {
            if (isset($metaIndex[$metaKey])) {
                $perBranch[$branchName] = (int) $metaIndex[$metaKey];
            }
        }

        $attributes = $this->flattenAttributes((array) Arr::get($p, 'attributes', []));

        return [
            'sku'                  => (string) ($p['sku'] ?? ''),
            'source_id'            => (string) Arr::get($p, $map['source_id'] ?? 'id'),
            'name'                 => (string) Arr::get($p, $map['name'] ?? 'name'),
            'brand'                => $brand,
            'model'                => $attributes['Model'] ?? $attributes['model'] ?? null,
            'category'             => $category,
            'subcategory'          => isset($categories[1]) ? $this->slugify($categories[1]['name'] ?? '') : null,
            'description'          => strip_tags((string) Arr::get($p, $map['description'] ?? 'short_description', '')),
            'price'                => $promoPrice ?: $price ?: 0,
            'price_promo'          => $promoPrice ?: null,
            'currency'             => $this->currencyFor($p),
            'stock_total'          => $stockTotal,
            'stock_by_branch_json' => $perBranch ?: null,
            'attributes_json'      => $attributes ?: null,
            'compatibility_json'   => $this->extractCompatibility($attributes, $metaIndex),
            'images_json'          => $images ?: null,
            'url'                  => (string) Arr::get($p, $map['url'] ?? 'permalink'),
            'is_active'            => ($p['status'] ?? 'publish') === 'publish',
            'is_promo'             => $isPromo,
            'margin_rank'          => 0,
            'synced_at'            => now(),
        ];
    }

    private function num(mixed $v): float
    {
        if ($v === null || $v === '') return 0.0;
        return (float) $v;
    }

    private function currencyFor(array $p): string
    {
        return strtoupper((string) ($p['currency'] ?? config('gadget.orders.currency_fallback', 'GEL')));
    }

    private function slugify(string $s): string
    {
        $s = trim(mb_strtolower($s));
        return $s === '' ? 'misc' : $s;
    }

    private function flattenAttributes(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $a) {
            $name = $a['name'] ?? $a['slug'] ?? null;
            if (! $name) continue;
            $vals = $a['options'] ?? [];
            $out[$name] = count($vals) === 1 ? $vals[0] : $vals;
        }
        return $out;
    }

    private function extractBrand(array $p, array $map): ?string
    {
        // 1. Dedicated brand taxonomy (perfect-brands-for-woocommerce, etc.)
        $brandTax = $map['brand_taxonomy'] ?? 'product_brand';
        if (isset($p[$brandTax]) && is_array($p[$brandTax]) && isset($p[$brandTax][0]['name'])) {
            return (string) $p[$brandTax][0]['name'];
        }

        // 2. Brand as an attribute (pa_brand)
        foreach ((array) ($p['attributes'] ?? []) as $a) {
            $slug = $a['slug'] ?? '';
            if ($slug === ($map['brand_attribute'] ?? 'pa_brand') || mb_stripos($a['name'] ?? '', 'brand') !== false) {
                $opts = $a['options'] ?? [];
                if (! empty($opts)) return (string) $opts[0];
            }
        }
        return null;
    }

    private function extractCompatibility(array $attributes, array $meta): ?array
    {
        $candidates = [];
        foreach ($attributes as $name => $value) {
            if (preg_match('/compat|fits|for/i', $name)) {
                $candidates[$name] = $value;
            }
        }
        foreach ($meta as $k => $v) {
            if (preg_match('/compat|fits|for/i', $k)) {
                $candidates[$k] = $v;
            }
        }
        return $candidates ?: null;
    }
}
