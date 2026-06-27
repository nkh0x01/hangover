<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Http\JsonErrorRenderer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Hard-stops requests from suspended or banned users with a 403 +
 * structured envelope ({@see JsonErrorRenderer}).
 *
 * The middleware sits AFTER auth + device-binding, so we know the
 * user is resolved. The status check is intentionally string-based
 * (rather than calling `isBlocked()`) so the middleware works for
 * any future enum additions.
 *
 * Pair with the `device.bound` middleware in `bootstrap/app.php` for
 * every authenticated route group. The Sanctum `auth:sanctum` guard
 * already runs first, so a missing user produces a 401 elsewhere.
 */
final class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && in_array($user->status, ['suspended', 'banned'], true)) {
            throw new HttpException(403, 'account.'.$user->status);
        }

        return $next($request);
    }
}
