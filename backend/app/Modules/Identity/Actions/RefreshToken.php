<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;
use App\Modules\Identity\Services\TokenIssuer;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class RefreshToken
{
    public function __construct(private TokenIssuer $issuer) {}

    /**
     * @return array{token: string, expires_at: string, abilities: array<int, string>}
     */
    public function execute(User $user, UserDevice $device): array
    {
        if ($device->revoked_at !== null) {
            throw new HttpException(401, 'auth.device_revoked');
        }

        $token = $user->currentAccessToken();
        $graceMinutes = (int) config('sanctum.refresh_grace_minutes');

        if ($token && $token->expires_at && CarbonImmutable::createFromMutable($token->expires_at)->addMinutes($graceMinutes)->isPast()) {
            throw new HttpException(401, 'auth.token_expired');
        }

        return $this->issuer->issue($user, $device, purpose: 'refresh');
    }
}
