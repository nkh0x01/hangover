<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Enforces token <-> device binding. The Sanctum PersonalAccessToken
 * row's name is `pat:{platform}:{device_uuid}`; this middleware verifies
 * the incoming X-Device-Id matches.
 */
final class EnsureDeviceBound
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceUuid = (string) $request->header('X-Device-Id', '');
        $token = $request->user()?->currentAccessToken();

        if ($deviceUuid === '' || $token === null) {
            throw new HttpException(401, 'auth.invalid_token');
        }

        $expectedSuffix = ':'.$deviceUuid;
        if (! str_ends_with((string) $token->name, $expectedSuffix)) {
            throw new HttpException(401, 'auth.device_revoked');
        }

        // Best-effort: confirm the device is still active.
        $active = UserDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('device_uuid', $deviceUuid)
            ->whereNull('revoked_at')
            ->exists();

        if (! $active) {
            throw new HttpException(401, 'auth.device_revoked');
        }

        return $next($request);
    }
}
