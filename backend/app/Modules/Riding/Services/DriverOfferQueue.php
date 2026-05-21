<?php

declare(strict_types=1);

namespace App\Modules\Riding\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

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
        $key = "ride:{$rideId}:rejects";
        $this->conn()->sadd($key, $driverId);
        $this->conn()->expire($key, self::TTL_SECONDS);
    }

    /**
     * @return array<int, int>
     */
    public function rejectedDrivers(int $rideId): array
    {
        $members = (array) $this->conn()->smembers("ride:{$rideId}:rejects");

        return array_map('intval', $members);
    }

    public function setCurrentOffer(int $rideId, int $driverId, int $ttlSeconds): void
    {
        $this->conn()->setex("ride:{$rideId}:current_offer", $ttlSeconds, (string) $driverId);
    }

    public function currentOffer(int $rideId): ?int
    {
        $value = $this->conn()->get("ride:{$rideId}:current_offer");

        return $value === null || $value === false ? null : (int) $value;
    }

    public function clearCurrentOffer(int $rideId): void
    {
        $this->conn()->del("ride:{$rideId}:current_offer");
    }

    public function clear(int $rideId): void
    {
        $this->conn()->del(["ride:{$rideId}:rejects", "ride:{$rideId}:current_offer"]);
    }
}
