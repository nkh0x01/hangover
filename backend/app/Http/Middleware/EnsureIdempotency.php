<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Idempotency\IdempotencyStore;
use App\Support\Idempotency\StoredResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Middleware: ensures idempotent POST/PATCH against authenticated user
 * scope. Requires (or generates) an Idempotency-Key header.
 *
 * Behaviour:
 *  - Same key + same body  -> replay stored response.
 *  - Same key + diff body  -> 409 idempotency.conflict.
 *  - New key               -> proceed, capture response, store 24h.
 */
final class EnsureIdempotency
{
    public function __construct(private readonly IdempotencyStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $clientKey = (string) $request->header('Idempotency-Key', '');
        if ($clientKey === '') {
            // Not required for all routes — only enforced via 'idempotent' alias.
            return $next($request);
        }

        $key = IdempotencyStore::buildKey($user->id, $request->path(), $clientKey);
        $bodySha = hash('sha256', $request->getContent());

        if ($prior = $this->store->find($key)) {
            if ($prior->bodySha !== $bodySha) {
                throw new HttpException(409, 'idempotency.conflict');
            }

            return new Response($prior->body, $prior->status, [
                'Content-Type' => $prior->contentType,
                'Idempotent-Replay' => 'true',
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            $body = $response instanceof JsonResponse
                ? $response->getContent() ?: '{}'
                : (string) $response->getContent();

            $this->store->put($key, new StoredResponse(
                status: $response->getStatusCode(),
                bodySha: $bodySha,
                body: $body,
                contentType: (string) $response->headers->get('Content-Type', 'application/json'),
            ), ttlSeconds: ((int) config('idempotency.ttl_hours', env('IDEMPOTENCY_TTL_HOURS', 24))) * 3600);
        }

        return $response;
    }
}
