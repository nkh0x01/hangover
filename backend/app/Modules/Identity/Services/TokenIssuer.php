<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;
use Carbon\CarbonImmutable;

/**
 * Issues device-bound Sanctum tokens. Token name encodes the platform
 * + device UUID so the EnsureDeviceBound middleware can match it.
 */
final class TokenIssuer
{
    public function issue(User $user, UserDevice $device, string $purpose = 'login'): array
    {
        $abilities = $this->abilitiesFor($user);

        // Revoke any earlier token for the same device.
        $user->tokens()
            ->where('name', $this->tokenName($device))
            ->delete();

        $expiresAt = CarbonImmutable::now()->addMinutes((int) config('sanctum.expiration'));

        $newToken = $user->createToken(
            name: $this->tokenName($device),
            abilities: $abilities,
            expiresAt: $expiresAt,
        );

        $device->update(['last_active_at' => now()]);

        return [
            'token' => $newToken->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'abilities' => $abilities,
        ];
    }

    public function tokenName(UserDevice $device): string
    {
        return sprintf('pat:%s:%s', $device->platform, $device->device_uuid);
    }

    /**
     * @return array<int, string>
     */
    public function abilitiesFor(User $user): array
    {
        return match ($user->type) {
            'customer' => ['customer'],
            'driver' => $user->relationLoaded('driver') && $user->driver?->status === 'approved'
                ? ['driver']
                : ['driver:onboarding'],
            'admin', 'dispatcher' => ['admin'],
            default => [],
        };
    }
}
