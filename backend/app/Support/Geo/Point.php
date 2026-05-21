<?php

declare(strict_types=1);

namespace App\Support\Geo;

use InvalidArgumentException;

/**
 * Immutable WGS-84 point. We use simple haversine math here; spatial
 * indexing lives in MySQL and Redis.
 */
final readonly class Point
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException("Invalid latitude: {$lat}");
        }
        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException("Invalid longitude: {$lng}");
        }
    }

    /**
     * Great-circle distance in metres.
     */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6_371_000.0;

        $lat1 = deg2rad($this->lat);
        $lat2 = deg2rad($other->lat);
        $deltaLat = deg2rad($other->lat - $this->lat);
        $deltaLng = deg2rad($other->lng - $this->lng);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }
}
