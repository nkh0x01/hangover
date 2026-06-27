<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Riding\Models\Ride;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Spatial columns (POINT NOT NULL SRID 4326 with a SPATIAL INDEX) exist only
 * on MySQL — SQLite omits them (see the migration MySQL guards). MySQL also
 * needs the value at insert time: a SPATIAL INDEX column is NOT NULL, so a
 * post-insert UPDATE can't work because the insert itself fails first. This
 * helper builds rides with the geometry already set on MySQL, and is a no-op
 * for the spatial part on SQLite.
 */
final class SpatialTestHelpers
{
    /**
     * Create a ride, setting pickup/dropoff geometry at insert time on MySQL.
     *
     * @param array<string, mixed> $attributes
     * @param array{0: float, 1: float} $pickup [lng, lat]
     * @param array{0: float, 1: float} $dropoff [lng, lat]
     */
    public static function createRide(
        array $attributes,
        array $pickup = [44.8271, 41.7151],
        array $dropoff = [44.8271, 41.7321],
    ): Ride {
        $ride = new Ride($attributes);

        if (self::requiresMysql()) {
            $ride->setAttribute('pickup_location', self::point($pickup[0], $pickup[1]));
            $ride->setAttribute('dropoff_location', self::point($dropoff[0], $dropoff[1]));
        }

        $ride->save();

        return $ride;
    }

    public static function requiresMysql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    private static function point(float $lng, float $lat): Expression
    {
        return DB::raw(sprintf("ST_GeomFromText('POINT(%F %F)', 4326)", $lng, $lat));
    }
}
