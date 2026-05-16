<?php

namespace App\Services\Gadget;

use App\Services\Gadget\Exceptions\WooApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Minimal WooCommerce REST API client.
 *
 *   GET    /products?per_page=100&page=2
 *   GET    /products/{id}
 *   POST   /orders
 *   PUT    /customers/{id}
 *
 * Auth: HTTP Basic with consumer_key + consumer_secret (over HTTPS).
 * Pagination: WC returns the cursor in `X-WP-TotalPages` and `Link` headers.
 * Retries: configurable exponential backoff on connect errors and 5xx.
 */
class WooCommerceClient
{
    private Client $http;
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('gadget');
        $this->http = new Client([
            'base_uri'    => rtrim($this->config['base_url'], '/') . rtrim($this->config['api_path'], '/') . '/',
            'timeout'     => (int) ($this->config['timeout'] ?? 20),
            'http_errors' => false,
            'verify'      => (bool) ($this->config['verify_tls'] ?? true),
            'auth'        => [$this->config['consumer_key'] ?? '', $this->config['consumer_secret'] ?? ''],
            'headers'     => ['Accept' => 'application/json'],
        ]);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, ['json' => $body]);
    }

    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, ['json' => $body]);
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->request('DELETE', $path, ['query' => $query]);
    }

    /**
     * Paginated iterator. Yields each page as an array of items until
     * the source runs out.
     *
     * @return \Generator<array<int, array>>
     */
    public function paginate(string $path, array $query = []): \Generator
    {
        $perPage = (int) ($query['per_page'] ?? $this->config['sync']['page_size'] ?? 100);
        $page    = 1;

        do {
            $items = $this->get($path, $query + ['per_page' => $perPage, 'page' => $page]);
            if (! is_array($items) || count($items) === 0) {
                return;
            }
            yield $items;
            $page++;
            // WooCommerce returns < per_page on the last page.
        } while (count($items) >= $perPage);
    }

    private function request(string $method, string $path, array $opts): array
    {
        $retries = max(0, (int) ($this->config['retries'] ?? 3));
        $attempt = 0;

        beginning:
        try {
            $res    = $this->http->request($method, ltrim($path, '/'), $opts);
            $status = $res->getStatusCode();
            $body   = (string) $res->getBody();
            $data   = $body === '' ? [] : (json_decode($body, true) ?: ['raw' => $body]);

            if ($status >= 500 && $attempt < $retries) {
                $this->backoff(++$attempt);
                goto beginning;
            }

            if ($status >= 400) {
                Log::warning('gadget.wc.http_error', [
                    'method' => $method, 'path' => $path, 'status' => $status, 'body' => $data,
                ]);
                throw new WooApiException(
                    $data['message'] ?? "WooCommerce returned HTTP $status",
                    $status,
                    is_array($data) ? $data : [],
                );
            }

            return is_array($data) ? $data : [];
        } catch (ConnectException $e) {
            if ($attempt < $retries) {
                $this->backoff(++$attempt);
                goto beginning;
            }
            throw new WooApiException('connect_failed: ' . $e->getMessage(), 0);
        } catch (GuzzleException $e) {
            throw new WooApiException('guzzle_error: ' . $e->getMessage(), 0);
        }
    }

    private function backoff(int $attempt): void
    {
        // 250ms, 500ms, 1s, 2s, ...
        usleep((int) (250_000 * (2 ** ($attempt - 1))));
    }
}
