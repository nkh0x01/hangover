<?php

namespace App\Services\Gadget\Resources;

use App\Services\Gadget\WooCommerceClient;

class Coupons
{
    public function __construct(private WooCommerceClient $client) {}

    /** @return array<int, array> */
    public function each(): \Generator
    {
        foreach ($this->client->paginate('coupons') as $page) {
            foreach ($page as $coupon) {
                yield $coupon;
            }
        }
    }

    public function get(int $id): ?array
    {
        $c = $this->client->get("coupons/$id");

        return $c ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $items = $this->client->get('coupons', ['code' => $code, 'per_page' => 1]);

        return $items[0] ?? null;
    }
}
