<?php

declare(strict_types=1);

namespace App\Modules\Riding\Services;

use App\Modules\Riding\Models\RideOffer;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Per-ride state held in Redis to coordinate the dispatch loop without
 * thrashing MySQL:
 *
 *   ride:{id}:rejects         SET of driver ids that already
 *                             rejected/timed-out — excluded from the
 *                             next candidate batch.
 *   ride:{id}:current_offer   driver id currently holding the offer
 *                             (TTL = offer window). Used to detect
 *                             timeouts without a separate scheduler.
 *
 * Keys auto-expire 10 minutes after the last write so a stale ride
 * never holds dispatcher resources hostage.
 */
final class DriverOfferQueue
{
    private const TTL_SECONDS = 600;

    private function conn(): Connection
    {
        return Redis::connection((string) config('geo.index.connection', 'geo'));
    }

    public function markRejected(int $rideId, int $driverId): void
    {
        try {
            $key = "ride:{$rideId}:rejects";
            $this->conn()->sadd($key, $driverId);
            $this->conn()->expire($key, self::TTL_SECONDS);
        } catch (Throwable $e) {
            $this->logUnavailable('markRejected', $e, [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    public function rejectedDrivers(int $rideId): array
    {
        $members = [];

        try {
            $members = (array) $this->conn()->smembers("ride:{$rideId}:rejects");
        } catch (Throwable $e) {
            $this->logUnavailable('rejectedDrivers', $e, ['ride_id' => $rideId]);
        }

        $dbMembers = RideOffer::query()
            ->where('ride_id', $rideId)
            ->whereIn('response', ['rejected', 'timeout'])
            ->pluck('driver_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_merge($members, $dbMembers))));
    }

    public function setCurrentOffer(int $rideId, int $driverId, int $ttlSeconds): void
    {
        try {
            $this->conn()->setex("ride:{$rideId}:current_offer", $ttlSeconds, (string) $driverId);
        } catch (Throwable $e) {
            $this->logUnavailable('setCurrentOffer', $e, [
                'ride_id' => $rideId,
                'driver_id' => $driverId,
            ]);
        }
    }

    public function currentOffer(int $rideId): ?int
    {
        try {
            $value = $this->conn()->get("ride:{$rideId}:current_offer");

            return $value === null || $value === false ? null : (int) $value;
        } catch (Throwable $e) {
            $this->logUnavailable('currentOffer', $e, ['ride_id' => $rideId]);

            return null;
        }
    }

    public function clearCurrentOffer(int $rideId): void
    {
        try {
            $this->conn()->del("ride:{$rideId}:current_offer");
        } catch (Throwable $e) {
            $this->logUnavailable('clearCurrentOffer', $e, ['ride_id' => $rideId]);
        }
    }

    public function clear(int $rideId): void
    {
        try {
            $this->conn()->del(["ride:{$rideId}:rejects", "ride:{$rideId}:current_offer"]);
        } catch (Throwable $e) {
            $this->logUnavailable('clear', $e, ['ride_id' => $rideId]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logUnavailable(string $operation, Throwable $e, array $context): void
    {
        Log::warning('dispatch.offer_queue_unavailable', $context + [
            'operation' => $operation,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
