<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clamps Accept-Language to one of the supported locales and sets it
 * for the duration of the request.
 */
final class LocalizeRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('app.supported_locales', ['ka', 'en', 'ru']);
        $fallback = (string) config('app.fallback_locale', 'en');

        $preferred = strtolower(substr((string) $request->header('Accept-Language', ''), 0, 2));

        $locale = in_array($preferred, $supported, true) ? $preferred : $fallback;

        app()->setLocale($locale);

        return $next($request);
    }
}
