<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Ulid;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches X-Request-Id to every request and response, and pushes it
 * into the log context so all log lines in this request are correlated.
 */
final class LogRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: Ulid::new();
        $request->headers->set('X-Request-Id', $requestId);

        Log::withContext(['request_id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
