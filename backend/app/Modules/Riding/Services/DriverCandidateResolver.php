<?php

declare(strict_types=1);

namespace App\Modules\Riding\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Services\DbNearbyDriverFinder;
use App\Modules\Geo\Services\NearbyDriverIndex;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Geo\Point;
use Illuminate\Support\Facades\Log;

/**
 * Returns dispatch candidates for a ride, sorted best-first.
 *
 * Phase 1.5 scoring is "closest first" — plenty for an MVP. Acceptance
 * rate, rating and "minutes since last offer" knobs ride alongside in
 * config so the algorithm can be tuned without code changes once
 * production data starts arriving.
 *
 * Skip rules (HARD):
 *   - driver.status != 'approved'
 *   - driver.online != true
 *   - driver.current_vehicle_id IS NULL
 *   - driver in the ride's reject set
 *   - driver has an active ride (active_driver_lock in rides)
 *   - driver location older than the Redis TTL or DB fallback window
 */
final readonly class DriverCandidateResolver
{
    public function __construct(
        private NearbyDriverIndex $index,
        private DbNearbyDriverFinder $dbFallback,
        private DriverOfferQueue $offerQueue,
    ) {}

    /**
     * @return array<int, array{driver: Driver, distance_m: float}>
     */
    public function resolve(Ride $ride, Point $pickup, float $radiusKm, int $limit = 20): array
    {
        $candidates = $this->index->nearby($ride->city_id, $pickup, $radiusKm, $limit * 3);
        if ($candidates === []) {
            $candidates = $this->dbFallback->nearby($ride->city_id, $pickup, $radiusKm, $limit * 3);
            if ($candidates !== []) {
                Log::channel('dispatch')->info('Using DB driver-location fallback', [
                    'ride_id' => $ride->id,
                    'candidate_count' => count($candidates),
                    'radius_km' => $radiusKm,
                ]);
            }
        }

        if ($candidates === []) {
            return [];
        }

        $rejectedIds = $this->offerQueue->rejectedDrivers($ride->id);
        $rejected = array_flip($rejectedIds);

        $driverIds = array_column($candidates, 'driver_id');

        $eligible = Driver::query()
            ->whereIn('id', $driverIds)
            ->where('status', 'approved')
            ->where('online', true)
            ->whereNotNull('current_vehicle_id')
            ->whereHas('currentVehicle', function ($q): void {
                $q->where('is_active', true)->whereNotNull('verified_at');
            })
            ->whereDoesntHave('rides', function ($q): void {
                $q->whereIn('status', $this->activeRideStatuses());
            })
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($candidates as $row) {
            $id = $row['driver_id'];
            if (isset($rejected[$id])) {
                continue;
            }
            $driver = $eligible->get($id);
            if (! $driver) {
                continue;
            }
            $out[] = ['driver' => $driver, 'distance_m' => (float) $row['distance_m']];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function activeRideStatuses(): array
    {
        return [
            RideStatus::Offered->value,
            RideStatus::Accepted->value,
            RideStatus::DriverArriving->value,
            RideStatus::DriverArrived->value,
            RideStatus::InProgress->value,
        ];
    }
}
