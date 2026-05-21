<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Returns 426 app.outdated when the X-App-Version is below the
 * configured minimum for the platform.
 */
final class EnforceAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $platform = strtolower((string) $request->header('X-Platform', ''));
        $version = (string) $request->header('X-App-Version', '');

        if ($platform === '' || $version === '') {
            return $next($request);
        }

        $min = config("app.min_app_version.$platform");
        if ($min && version_compare($version, $min, '<')) {
            throw new HttpException(426, 'app.outdated');
        }

        return $next($request);
    }
}
