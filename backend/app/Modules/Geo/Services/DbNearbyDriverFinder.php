<?php

declare(strict_types=1);

namespace App\Modules\Geo\Services;

use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Geo\Point;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DbNearbyDriverFinder
{
    /**
     * @return array<int, array{driver_id: int, lat: float, lng: float, distance_m: float}>
     */
    public function nearby(int $cityId, Point $center, float $radiusKm, int $limit = 20): array
    {
        $recentSeconds = (int) config('geo.index.fallback_recent_seconds', 300);
        $cutoff = now()->subSeconds(max(30, $recentSeconds));

        return DB::getDriverName() === 'mysql'
            ? $this->nearbyFromMysqlLiveLocations($cityId, $center, $radiusKm, $limit, $cutoff)
            : $this->nearbyFromActiveShifts($cityId, $center, $radiusKm, $limit, $cutoff);
    }

    /**
     * @return array<int, array{driver_id: int, lat: float, lng: float, distance_m: float}>
     */
    private function nearbyFromMysqlLiveLocations(int $cityId, Point $center, float $radiusKm, int $limit, \DateTimeInterface $cutoff): array
    {
        $latestLocations = DB::table('live_locations')
            ->selectRaw('MAX(id) AS id')
            ->where('recorded_at', '>=', $cutoff)
            ->groupBy('driver_id');

        $distanceSql = 'ST_Distance_Sphere(ll.location, ST_GeomFromText(CONCAT(\'POINT(\', ?, \' \', ?, \')\'), 4326))';

        /** @var Collection<int, object{driver_id: int, lat: numeric-string|float, lng: numeric-string|float, distance_m: numeric-string|float}> $rows */
        $rows = DB::table('live_locations as ll')
            ->joinSub($latestLocations, 'latest_locations', fn ($join) => $join->on('ll.id', '=', 'latest_locations.id'))
            ->join('drivers as d', 'd.id', '=', 'll.driver_id')
            ->join('vehicles as v', 'v.id', '=', 'd.current_vehicle_id')
            ->where('d.city_id', $cityId)
            ->where('d.status', 'approved')
            ->where('d.online', true)
            ->where('v.is_active', true)
            ->whereNotNull('v.verified_at')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('rides as active_rides')
                    ->whereColumn('active_rides.driver_id', 'd.id')
                    ->whereIn('active_rides.status', $this->activeRideStatuses());
            })
            ->whereRaw($distanceSql.' <= ?', [$center->lng, $center->lat, $radiusKm * 1000])
            ->selectRaw(
                "ll.driver_id, ST_Y(ll.location) AS lat, ST_X(ll.location) AS lng, {$distanceSql} AS distance_m",
                [$center->lng, $center->lat],
            )
            ->orderBy('distance_m')
            ->limit($limit)
            ->get();

        return $rows
            ->map(fn (object $row): array => [
                'driver_id' => (int) $row->driver_id,
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'distance_m' => (float) $row->distance_m,
            ])
            ->all();
    }

    /**
     * @return array<int, array{driver_id: int, lat: float, lng: float, distance_m: float}>
     */
    private function nearbyFromActiveShifts(int $cityId, Point $center, float $radiusKm, int $limit, \DateTimeInterface $cutoff): array
    {
        $latestShifts = DB::table('driver_shifts')
            ->selectRaw('MAX(id) AS id')
            ->whereNull('ended_at')
            ->where('started_at', '>=', $cutoff)
            ->groupBy('driver_id');

        $rows = DB::table('driver_shifts as ds')
            ->joinSub($latestShifts, 'latest_shifts', fn ($join) => $join->on('ds.id', '=', 'latest_shifts.id'))
            ->join('drivers as d', 'd.id', '=', 'ds.driver_id')
            ->join('vehicles as v', 'v.id', '=', 'd.current_vehicle_id')
            ->where('d.city_id', $cityId)
            ->where('d.status', 'approved')
            ->where('d.online', true)
            ->where('v.is_active', true)
            ->whereNotNull('v.verified_at')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('rides as active_rides')
                    ->whereColumn('active_rides.driver_id', 'd.id')
                    ->whereIn('active_rides.status', $this->activeRideStatuses());
            })
            ->get(['ds.driver_id', 'ds.started_lat as lat', 'ds.started_lng as lng'])
            ->map(function (object $row) use ($center): array {
                $point = new Point((float) $row->lat, (float) $row->lng);

                return [
                    'driver_id' => (int) $row->driver_id,
                    'lat' => $point->lat,
                    'lng' => $point->lng,
                    'distance_m' => $center->distanceTo($point),
                ];
            })
            ->filter(fn (array $row): bool => $row['distance_m'] <= $radiusKm * 1000)
            ->sortBy('distance_m')
            ->take($limit)
            ->values();

        return $rows->all();
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
