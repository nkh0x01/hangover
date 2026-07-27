<?php

namespace App\Services\Gadget;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client for the new gadget.ge API (Laravel, Bearer token) — products/stock
 * (read) and orders (write, ability `orders:write`). Replaces the WooCommerce
 * REST source.
 *
 *   GET  /products?page&per_page&search&category&sku&in_stock&updated_since
 *   GET  /products/{sku}
 *   POST /orders                    (idempotent on external_id)
 *   GET  /orders/{id}
 *   POST /orders/{id}/status
 *
 * List/search response: { "data": [ <product> ], "meta": { current_page, last_page, ... } }
 */
class CatalogApiClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => rtrim((string) config('gadget.api.url'), '/') . '/',
            'timeout' => (int) config('gadget.api.timeout', 25),
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . (string) config('gadget.api.token'),
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return ! empty(config('gadget.api.url')) && ! empty(config('gadget.api.token'));
    }

    // ---------------------------------------------------------------- products

    /** GET /products with query params → decoded body { data, meta } or null. */
    public function products(array $params = []): ?array
    {
        try {
            $res = $this->http->get('products', ['query' => $params]);
            if ($res->getStatusCode() >= 400) {
                Log::warning('gadget.api.products.http', ['status' => $res->getStatusCode(), 'params' => $params]);

                return null;
            }

            return json_decode((string) $res->getBody(), true) ?: null;
        } catch (Throwable $e) {
            Log::error('gadget.api.products.exception', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /** GET /products/{sku} → the single product array (the `data` payload), or null. */
    public function product(string $sku): ?array
    {
        try {
            $res = $this->http->get('products/' . rawurlencode($sku));
            if ($res->getStatusCode() >= 400) {
                return null;
            }
            $j = json_decode((string) $res->getBody(), true);

            return $j['data'] ?? null;
        } catch (Throwable $e) {
            Log::error('gadget.api.product.exception', ['sku' => $sku, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Iterate every product across all pages. Stops on the first failed page
     * so a mid-sync API hiccup doesn't mass-deactivate the mirror.
     *
     * @return \Generator<int, array>
     */
    public function each(array $filters = []): \Generator
    {
        $perPage = (int) config('gadget.api.page_size', 100);
        $page = 1;
        $lastPage = 1;

        do {
            $resp = $this->products(array_merge($filters, ['page' => $page, 'per_page' => $perPage]));
            if (! is_array($resp) || ! isset($resp['data'])) {
                throw new \RuntimeException("catalog api page {$page} failed");
            }

            foreach ((array) $resp['data'] as $p) {
                yield $p;
            }

            $lastPage = (int) ($resp['meta']['last_page'] ?? $page);
            $page++;
        } while ($page <= $lastPage);
    }

    // ------------------------------------------------------------------ orders

    /** POST /orders — idempotent on external_id. Returns the `data` payload or null. */
    public function createOrder(array $payload): ?array
    {
        try {
            $res = $this->http->post('orders', ['json' => $payload]);
            $body = json_decode((string) $res->getBody(), true) ?: [];
            if ($res->getStatusCode() >= 400) {
                Log::warning('gadget.api.order.create.http', [
                    'status' => $res->getStatusCode(),
                    'external_id' => $payload['external_id'] ?? null,
                    'body' => $body,
                ]);

                return null;
            }

            return $body['data'] ?? $body;
        } catch (Throwable $e) {
            Log::error('gadget.api.order.create.exception', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /** GET /orders/{id} → order `data` or null. */
    public function getOrder(string $id): ?array
    {
        try {
            $res = $this->http->get('orders/' . rawurlencode($id));
            if ($res->getStatusCode() >= 400) {
                return null;
            }
            $j = json_decode((string) $res->getBody(), true);

            return $j['data'] ?? null;
        } catch (Throwable $e) {
            Log::error('gadget.api.order.get.exception', ['id' => $id, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /** POST /orders/{id}/status — e.g. mark paid after BOG. Returns `data` or null. */
    public function setOrderStatus(string $id, string $status): ?array
    {
        try {
            $res = $this->http->post('orders/' . rawurlencode($id) . '/status', ['json' => ['status' => $status]]);
            if ($res->getStatusCode() >= 400) {
                return null;
            }
            $j = json_decode((string) $res->getBody(), true);

            return $j['data'] ?? null;
        } catch (Throwable $e) {
            Log::error('gadget.api.order.status.exception', ['id' => $id, 'msg' => $e->getMessage()]);

            return null;
        }
    }
}
