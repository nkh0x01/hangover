<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Profile;

use App\Modules\Identity\Actions\RegisterFcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Manages mobile-device records for the authenticated user. Phase 2
 * extends with /me/devices index + revoke endpoints; this controller
 * currently only handles FCM-token registration since that's the only
 * device action needed to ship push notifications.
 */
final class DeviceController extends Controller
{
    public function registerFcmToken(Request $request, RegisterFcmToken $action): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
            'voip_token' => ['nullable', 'string', 'max:255'],
        ]);

        $device = $action->execute(
            user: $request->user(),
            deviceUuid: (string) $request->header('X-Device-Id'),
            fcmToken: (string) $data['fcm_token'],
            voipToken: $data['voip_token'] ?? null,
        );

        return new JsonResponse([
            'data' => [
                'device_id' => $device->device_uuid,
                'push_enabled' => $device->push_enabled,
                'registered_at' => $this->dateTimeString($device->last_active_at),
            ],
        ]);
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->toIso8601String()
            : Carbon::parse((string) $value)->toIso8601String();
    }
}
