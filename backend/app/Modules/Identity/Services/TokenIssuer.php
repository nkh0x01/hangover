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
        $abilities = $this->abilitiesFor($user, $purpose);

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
    public function abilitiesFor(User $user, ?string $purpose = null): array
    {
        if ($this->isDriverPurpose($purpose)) {
            return $this->driverAbilitiesFor($user);
        }

        return match ($user->type) {
            'customer' => ['customer'],
            'driver' => $this->driverAbilitiesFor($user),
            'admin', 'dispatcher' => ['admin'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function driverAbilitiesFor(User $user): array
    {
        $driver = $user->relationLoaded('driver')
            ? $user->driver
            : $user->driver()->first();

        return $driver?->status === 'approved'
            ? ['driver']
            : ['driver:onboarding'];
    }

    private function isDriverPurpose(?string $purpose): bool
    {
        return in_array($purpose, ['driver_signup'], true);
    }
}
