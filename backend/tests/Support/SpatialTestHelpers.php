<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * MySQL-only helpers: when the test connection is SQLite, the spatial
 * column doesn't exist (see migration MySQL guards), so the UPDATE
 * would fail. SQLite test runs simply skip the location set — tests
 * that actually need spatial behaviour gate themselves with
 * {@see SpatialTestHelpers::requiresMysql()}.
 */
final class SpatialTestHelpers
{
    public static function setRidePoints(int $rideId, float $pickupLng, float $pickupLat, float $dropoffLng, float $dropoffLat): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement(
            'UPDATE rides SET pickup_location = ST_SRID(POINT(?,?),4326), dropoff_location = ST_SRID(POINT(?,?),4326) WHERE id = ?',
            [$pickupLng, $pickupLat, $dropoffLng, $dropoffLat, $rideId],
        );
    }

    public static function requiresMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }
}
