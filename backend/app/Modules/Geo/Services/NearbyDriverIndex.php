<?php

declare(strict_types=1);

namespace App\Modules\Geo\Services;

use App\Support\Geo\Point;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Hot, Redis-backed index of online drivers per city. Phase 3's
 * DispatchService is the primary consumer; admin live-map is the other.
 *
 * Keys:
 *   drivers:online:{cityId}       - GEO set of driver:{id} -> point
 *   driver:{id}:meta              - hash with heading/speed/recorded_at
 */
final class NearbyDriverIndex
{
    private const META_TTL = 60;

    private function connection(): Connection
    {
        return Redis::connection((string) config('geo.index.connection', 'geo'));
    }

    private function key(int $cityId): string
    {
        return sprintf('%s:%d', (string) config('geo.index.set_prefix', 'drivers:online'), $cityId);
    }

    private function metaKey(int $driverId): string
    {
        return sprintf('%s:%d:meta', (string) config('geo.index.driver_meta_prefix', 'driver'), $driverId);
    }

    public function upsert(int $cityId, int $driverId, Point $point, int $heading, float $speedKmh, \DateTimeInterface $recordedAt): void
    {
        $conn = $this->connection();

        $conn->geoadd($this->key($cityId), $point->lng, $point->lat, "driver:{$driverId}");

        $conn->hmset($this->metaKey($driverId), [
            'heading' => (string) $heading,
            'speed' => (string) $speedKmh,
            'recorded_at' => $recordedAt->format('c'),
            'city_id' => (string) $cityId,
        ]);
        $conn->expire($this->metaKey($driverId), self::META_TTL);
    }

    public function remove(int $cityId, int $driverId): void
    {
        $conn = $this->connection();
        $conn->zrem($this->key($cityId), "driver:{$driverId}");
        $conn->del($this->metaKey($driverId));
    }

    /**
     * @return array<int, array{driver_id: int, lat: float, lng: float, distance_m: float}>
     */
    public function nearby(int $cityId, Point $center, float $radiusKm, int $limit = 20): array
    {
        /** @var array<int, array<int, mixed>> $rows */
        $rows = $this->connection()->command('geosearch', [
            $this->key($cityId),
            'FROMLONLAT',
            $center->lng,
            $center->lat,
            'BYRADIUS',
            $radiusKm,
            'km',
            'ASC',
            'COUNT',
            $limit,
            'WITHCOORD',
            'WITHDIST',
        ]) ?: [];

        $out = [];
        foreach ($rows as $row) {
            // [member, distance, [lng, lat]]
            $member = (string) $row[0];
            $distanceKm = (float) $row[1];
            $coord = $row[2];

            $id = (int) substr($member, strlen('driver:'));
            $out[] = [
                'driver_id' => $id,
                'lat' => (float) $coord[1],
                'lng' => (float) $coord[0],
                'distance_m' => $distanceKm * 1000,
            ];
        }

        return $out;
    }
}
