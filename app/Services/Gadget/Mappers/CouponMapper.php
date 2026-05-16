<?php

namespace App\Services\Gadget\Mappers;

class CouponMapper
{
    public function fromWoo(array $c): array
    {
        return [
            'source_id'               => (string) ($c['id'] ?? ''),
            'code'                    => (string) ($c['code'] ?? ''),
            'discount_type'           => (string) ($c['discount_type'] ?? 'percent'),
            'amount'                  => (float) ($c['amount'] ?? 0),
            'min_amount'              => $this->optionalDecimal($c['minimum_amount'] ?? null),
            'max_amount'              => $this->optionalDecimal($c['maximum_amount'] ?? null),
            'expires_at'              => ! empty($c['date_expires']) ? \Carbon\Carbon::parse($c['date_expires'])->utc() : null,
            'product_skus_json'       => $this->productSkus($c),
            'product_categories_json' => $c['product_categories']    ?? [],
            'excluded_skus_json'      => $c['excluded_product_ids']  ?? [],
            'individual_use'          => (bool) ($c['individual_use'] ?? false),
            'free_shipping'           => (bool) ($c['free_shipping']  ?? false),
            'usage_limit'             => $c['usage_limit'] ?? null,
            'usage_count'             => (int) ($c['usage_count']     ?? 0),
            'description'             => (string) ($c['description']  ?? ''),
            'is_active'               => true,
            'synced_at'               => now(),
        ];
    }

    private function optionalDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '' || $v === '0' || $v === '0.00') return null;
        return (float) $v;
    }

    private function productSkus(array $c): array
    {
        // WooCommerce only stores product_ids in coupons; the SKU
        // mapping is resolved on consumption time, not on sync.
        return [
            'product_ids' => $c['product_ids'] ?? [],
        ];
    }
}
