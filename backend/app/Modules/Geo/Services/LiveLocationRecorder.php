<?php

declare(strict_types=1);

namespace App\Modules\Geo\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\LiveLocation;
use App\Support\Geo\Point;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class LiveLocationRecorder
{
    public function record(
        Driver $driver,
        Point $location,
        int $heading = 0,
        float $speedKmh = 0.0,
        ?float $accuracyM = null,
        ?int $batteryPct = null,
        ?int $rideId = null,
        ?DateTimeInterface $recordedAt = null,
        string $source = 'mobile_gps',
    ): LiveLocation {
        $recordedAt ??= now();

        if (DB::getDriverName() === 'mysql') {
            DB::insert(
                'INSERT INTO live_locations
                    (driver_id, ride_id, recorded_at, heading, speed_kmh, accuracy_m, battery_pct, source, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromText(CONCAT(\'POINT(\', ?, \' \', ?, \')\'), 4326))',
                [
                    $driver->id,
                    $rideId,
                    $recordedAt,
                    $heading,
                    $speedKmh,
                    $accuracyM,
                    $batteryPct,
                    $source,
                    $location->lng,
                    $location->lat,
                ],
            );

            return LiveLocation::query()->findOrFail((int) DB::getPdo()->lastInsertId());
        }

        return LiveLocation::create([
            'driver_id' => $driver->id,
            'ride_id' => $rideId,
            'recorded_at' => $recordedAt,
            'heading' => $heading,
            'speed_kmh' => $speedKmh,
            'accuracy_m' => $accuracyM,
            'battery_pct' => $batteryPct,
            'source' => $source,
        ]);
    }
}
