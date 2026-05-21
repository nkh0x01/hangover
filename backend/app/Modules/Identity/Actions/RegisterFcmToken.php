<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;

/**
 * Persists a fresh FCM / APNs token for the user's current device.
 * The Communication module's PushService picks the latest token off
 * `user_devices.fcm_token` when delivering offers, ride-status pushes,
 * and arrival alerts.
 *
 * Token may also rotate while the app is running (FCM emits a
 * `onTokenRefresh` stream); the mobile client is expected to call this
 * endpoint whenever a new token is observed.
 */
final readonly class RegisterFcmToken
{
    public function execute(User $user, string $deviceUuid, string $fcmToken, ?string $voipToken = null): UserDevice
    {
        $device = UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->firstOrFail();

        $device->update([
            'fcm_token' => $fcmToken,
            'voip_token' => $voipToken,
            'push_enabled' => true,
            'last_active_at' => now(),
        ]);

        return $device->fresh();
    }
}
