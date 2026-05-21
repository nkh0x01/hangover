<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Stores idempotency outcomes for 24h. Keyed by user + route + key, the
 * value is a SHA-256 of the request body plus the serialised response —
 * allowing safe replays and rejection of body drift.
 */
final class IdempotencyStore
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function find(string $key): ?StoredResponse
    {
        $blob = $this->cache->get($key);
        if ($blob === null) {
            return null;
        }

        return StoredResponse::fromArray($blob);
    }

    public function put(string $key, StoredResponse $response, int $ttlSeconds): void
    {
        $this->cache->put($key, $response->toArray(), $ttlSeconds);
    }

    public static function buildKey(int $userId, string $route, string $clientKey): string
    {
        return sprintf('idem:%d:%s:%s', $userId, sha1($route), $clientKey);
    }
}
