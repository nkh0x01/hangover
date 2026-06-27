<?php

declare(strict_types=1);

namespace App\Modules\Riding\Listeners;

use App\Modules\Communication\Contracts\PushGateway;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;
use App\Modules\Riding\Events\RideOffered;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener that turns a {@see RideOffered} broadcast into an actual
 * FCM push targeted at the driver's most recent device token.
 *
 * Why a listener instead of inlining in DispatchService:
 *   - The dispatch path stays sync + transactional.
 *   - Push delivery happens off the request thread (queued), so a
 *     dropped Firebase request can't slow ride offers.
 *   - Tests can fake the gateway without touching the dispatcher.
 *
 * The listener is queued onto the `realtime` queue (same lane as the
 * dispatcher) so order-of-arrival closely matches the broadcast.
 */
final class SendOfferPush implements ShouldQueue
{
    public string $queue = 'realtime';

    public function __construct(
        private readonly PushGateway $gateway,
    ) {}

    public function handle(RideOffered $event): void
    {
        $driverUser = User::query()->where('ulid', $event->driverUlid)->first();
        if ($driverUser === null) {
            return;
        }

        // Drop pushes for stale offers — the driver UI will refuse them anyway.
        if (isset($event->payload['expires_at'])) {
            try {
                $expiresAt = CarbonImmutable::parse((string) $event->payload['expires_at']);
                $ttl = (int) config('push.offer_ttl_seconds', 15);
                if ($expiresAt->isBefore(CarbonImmutable::now()->subSeconds($ttl))) {
                    Log::channel('push')->info('Skipping stale offer push', [
                        'ride_ulid' => $event->rideUlid,
                        'driver_ulid' => $event->driverUlid,
                    ]);

                    return;
                }
            } catch (\Throwable) {
                // Bad timestamp — fall through and send anyway.
            }
        }

        /** @var UserDevice|null $device */
        $device = UserDevice::query()
            ->where('user_id', $driverUser->id)
            ->where('push_enabled', true)
            ->whereNotNull('fcm_token')
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->first();

        if ($device === null || $device->fcm_token === null || $device->fcm_token === '') {
            Log::channel('push')->info('No FCM token for driver', [
                'driver_ulid' => $event->driverUlid,
            ]);

            return;
        }

        $pickup = (array) ($event->payload['pickup'] ?? []);
        $dropoff = (array) ($event->payload['dropoff'] ?? []);
        $fare = (array) ($event->payload['fare'] ?? []);

        $result = $this->gateway->send(
            token: $device->fcm_token,
            title: 'New ride offer',
            body: sprintf(
                '%s → %s · %s',
                (string) ($pickup['address'] ?? 'Pickup'),
                (string) ($dropoff['address'] ?? 'Destination'),
                $this->formatFare($fare),
            ),
            data: [
                'kind' => 'ride.offered',
                'ride_ulid' => (string) $event->rideUlid,
                'driver_ulid' => (string) $event->driverUlid,
                'expires_at' => (string) ($event->payload['expires_at'] ?? ''),
                'distance_to_pickup_m' => (string) ($event->payload['distance_to_pickup_m'] ?? '0'),
            ],
        );

        if (! $result->delivered) {
            Log::channel('push')->warning('Offer push failed', [
                'driver_ulid' => $event->driverUlid,
                'error' => $result->errorMessage,
                'token_invalid' => $result->tokenInvalid,
            ]);

            if ($result->tokenInvalid) {
                // Purge the invalid token so we don't keep retrying it.
                $device->update(['fcm_token' => null, 'push_enabled' => false]);
            }
        }
    }

    /**
     * @param array<string, mixed> $fare
     */
    private function formatFare(array $fare): string
    {
        $amount = (float) ($fare['amount'] ?? 0);
        $currency = (string) ($fare['currency'] ?? '');

        return $currency === '' ? number_format($amount, 2) : "{$currency} ".number_format($amount, 2);
    }
}
