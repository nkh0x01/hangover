<?php

declare(strict_types=1);

namespace App\Modules\Riding\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Services\NearbyDriverIndex;
use App\Modules\Riding\Models\Ride;
use App\Support\Geo\Point;

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
 *   - driver heartbeat older than `geo.index.meta_ttl_seconds` (the
 *     Redis index expires; if no entry, candidate isn't returned at all)
 */
final readonly class DriverCandidateResolver
{
    public function __construct(
        private NearbyDriverIndex $index,
        private DriverOfferQueue $offerQueue,
    ) {}

    /**
     * @return array<int, array{driver: Driver, distance_m: float}>
     */
    public function resolve(Ride $ride, Point $pickup, float $radiusKm, int $limit = 20): array
    {
        $candidates = $this->index->nearby($ride->city_id, $pickup, $radiusKm, $limit * 3);
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
            ->whereDoesntHave('rides', function ($q): void {
                $q->whereIn('status', [
                    'offered', 'accepted', 'driver_arriving', 'driver_arrived', 'in_progress',
                ]);
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
}
