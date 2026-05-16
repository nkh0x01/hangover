<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class WebhookRateLimit
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next)
    {
        // 600 per minute per platform — generous, but a brake on bursty
        // retries from misconfigured Meta apps.
        $platform = $request->route('channel', 'unknown');
        $key = 'webhook:' . $platform;

        if ($this->limiter->tooManyAttempts($key, 600)) {
            return response('too_many_requests', 429);
        }

        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
