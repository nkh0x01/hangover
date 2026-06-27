<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Driver\Services\DriverVerificationPresenter;
use App\Modules\Geo\Services\LiveLocationRecorder;
use App\Modules\Geo\Services\NearbyDriverIndex;
use App\Support\Exceptions\DomainException;
use App\Support\Geo\Point;
use Illuminate\Support\Facades\DB;

final readonly class SetDriverOnline
{
    public function __construct(
        private NearbyDriverIndex $index,
        private LiveLocationRecorder $locations,
        private DriverVerificationPresenter $verification,
    ) {}

    public function execute(Driver $driver, Point $location, ?int $vehicleId = null): Driver
    {
        if (! $this->verification->canAcceptOffers($driver)) {
            throw new class('Driver not approved.') extends DomainException
            {
                public function code(): string
                {
                    return 'driver.not_approved';
                }

                public function status(): int
                {
                    return 403;
                }
            };
        }

        $vehicle = $this->resolveVehicle($driver, $vehicleId);
        $now = now();

        DB::transaction(function () use ($driver, $location, $now, $vehicle): void {
            $lockedDriver = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();
            $onlineSince = $lockedDriver->online && $lockedDriver->online_since !== null
                ? $lockedDriver->online_since
                : $now;

            $lockedDriver->update([
                'online' => true,
                'online_since' => $onlineSince,
                'current_vehicle_id' => $vehicle->id,
            ]);

            $activeShift = $lockedDriver->shifts()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($activeShift === null) {
                $lockedDriver->shifts()->create([
                    'started_at' => $onlineSince,
                    'started_lat' => $location->lat,
                    'started_lng' => $location->lng,
                ]);
            }

            $this->locations->record(
                driver: $lockedDriver,
                location: $location,
                heading: 0,
                speedKmh: 0.0,
                recordedAt: $now,
            );
        });

        $this->index->upsert(
            cityId: $driver->city_id,
            driverId: $driver->id,
            point: $location,
            heading: 0,
            speedKmh: 0.0,
            recordedAt: now(),
        );

        return $driver->refresh();
    }

    private function resolveVehicle(Driver $driver, ?int $vehicleId): Vehicle
    {
        $vehicleId ??= $driver->current_vehicle_id;

        $vehicle = $vehicleId !== null
            ? Vehicle::query()
                ->where('driver_id', $driver->id)
                ->whereKey($vehicleId)
                ->where('is_active', true)
                ->first()
            : null;

        $vehicle ??= Vehicle::query()
            ->where('driver_id', $driver->id)
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        if ($vehicle !== null) {
            return $vehicle;
        }

        throw new class('No active vehicle on file.') extends DomainException
        {
            public function code(): string
            {
                return 'driver.no_active_vehicle';
            }

            public function status(): int
            {
                return 403;
            }
        };
    }
}
