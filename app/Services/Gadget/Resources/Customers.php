<?php

namespace App\Services\Gadget\Resources;

use App\Services\Gadget\WooCommerceClient;

class Customers
{
    public function __construct(private WooCommerceClient $client) {}

    public function get(int $id): ?array
    {
        $c = $this->client->get("customers/$id");
        return $c ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $items = $this->client->get('customers', ['email' => $email, 'per_page' => 1]);
        return $items[0] ?? null;
    }

    /**
     * WooCommerce can't filter customers by phone directly via REST.
     * We search all customers with a `search` term — typical for
     * stores with modest customer counts. For large catalogs this
     * should be replaced with a custom WP endpoint.
     */
    public function findByPhone(string $phone): ?array
    {
        $needle = preg_replace('/\D+/', '', $phone);
        if ($needle === '') return null;

        $items = $this->client->get('customers', ['search' => $needle, 'per_page' => 10]);
        foreach ((array) $items as $c) {
            $cp = preg_replace('/\D+/', '', (string) ($c['billing']['phone'] ?? ''));
            if ($cp !== '' && str_ends_with($cp, substr($needle, -7))) {
                return $c;
            }
        }
        return null;
    }

    public function create(array $payload): array
    {
        return $this->client->post('customers', $payload);
    }

    public function update(int $id, array $payload): array
    {
        return $this->client->put("customers/$id", $payload);
    }
}
