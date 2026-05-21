<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;
use App\Modules\Identity\Services\OtpService;
use App\Modules\Identity\Services\TokenIssuer;
use App\Support\Ulid;
use Illuminate\Support\Facades\DB;

/**
 * Verifies an OTP, finds-or-creates the user, upserts the device row,
 * and issues a Sanctum token bound to that device.
 */
final readonly class VerifyOtp
{
    public function __construct(
        private OtpService $otp,
        private TokenIssuer $issuer,
    ) {}

    /**
     * @param array<string, mixed> $deviceMeta device_uuid, platform, app_version, os_version, fcm_token
     * @return array{user: User, token: string, expires_at: string, abilities: array<int, string>, is_new: bool}
     */
    public function execute(string $phoneE164, string $code, string $purpose, array $deviceMeta): array
    {
        $this->otp->verify($phoneE164, $code, $purpose);

        return DB::transaction(function () use ($phoneE164, $purpose, $deviceMeta): array {
            $type = $purpose === 'driver_signup' ? 'driver' : 'customer';

            $user = User::query()
                ->where('phone_e164', $phoneE164)
                ->lockForUpdate()
                ->first();

            $isNew = false;
            if (! $user) {
                $isNew = true;
                $user = User::create([
                    'ulid' => Ulid::new(),
                    'type' => $type,
                    'phone_e164' => $phoneE164,
                    'phone_verified_at' => now(),
                    'status' => 'active',
                    'referral_code' => strtoupper(substr(Ulid::new(), 0, 8)),
                ]);
            } elseif ($user->phone_verified_at === null) {
                $user->update(['phone_verified_at' => now()]);
            }

            $device = UserDevice::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_uuid' => (string) $deviceMeta['device_uuid'],
                ],
                [
                    'platform' => (string) $deviceMeta['platform'],
                    'app_version' => $deviceMeta['app_version'] ?? null,
                    'os_version' => $deviceMeta['os_version'] ?? null,
                    'fcm_token' => $deviceMeta['fcm_token'] ?? null,
                    'voip_token' => $deviceMeta['voip_token'] ?? null,
                    'last_active_at' => now(),
                    'revoked_at' => null,
                ],
            );

            $token = $this->issuer->issue($user, $device);

            return array_merge(['user' => $user, 'is_new' => $isNew], $token);
        });
    }
}
