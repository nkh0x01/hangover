<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\LiveLocation;
use App\Modules\Geo\Services\NearbyDriverIndex;
use App\Modules\Riding\Events\DriverLocationUpdated;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Geo\Point;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Accept one GPS sample from the driver app. Plausibility-checks the
 * sample, writes it to the hot Redis index (so dispatch can see it
 * within ~50ms), and persists a row in live_locations.
 *
 * If the driver has an active ride, broadcast the position to
 * private-ride.{ulid} so the customer's marker moves in realtime.
 */
final readonly class IngestLocationHeartbeat
{
    public function __construct(private NearbyDriverIndex $index) {}

    public function execute(
        Driver $driver,
        Point $location,
        int $heading,
        float $speedKmh,
        ?float $accuracyM,
        ?int $batteryPct,
        ?CarbonImmutable $recordedAt = null,
    ): void {
        $now = $recordedAt ?? CarbonImmutable::now();

        // Plausibility: implausibly fast samples are dropped.
        $maxSpeed = (float) config('geo.plausibility.max_speed_kmh', 80);
        if ($speedKmh > $maxSpeed) {
            return;
        }

        $this->index->upsert(
            cityId: $driver->city_id,
            driverId: $driver->id,
            point: $location,
            heading: $heading,
            speedKmh: $speedKmh,
            recordedAt: $now,
        );

        $activeRide = $this->activeRideFor($driver);

        // Persist canonical history (async-friendly insert; small payload).
        $row = LiveLocation::create([
            'driver_id' => $driver->id,
            'ride_id' => $activeRide?->id,
            'recorded_at' => $now,
            'heading' => $heading,
            'speed_kmh' => $speedKmh,
            'accuracy_m' => $accuracyM,
            'battery_pct' => $batteryPct,
            'source' => 'mobile_gps',
        ]);

        DB::statement(
            'UPDATE live_locations SET location = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
            [$location->lng, $location->lat, $row->id],
        );

        if ($activeRide) {
            event(new DriverLocationUpdated(
                rideUlid: $activeRide->ulid,
                lat: $location->lat,
                lng: $location->lng,
                heading: $heading,
                speedKmh: $speedKmh,
                at: $now,
            ));
        }
    }

    private function activeRideFor(Driver $driver): ?Ride
    {
        return Ride::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [
                RideStatus::Accepted->value,
                RideStatus::DriverArriving->value,
                RideStatus::DriverArrived->value,
                RideStatus::InProgress->value,
            ])
            ->first();
    }
}
