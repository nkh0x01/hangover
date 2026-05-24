<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Services\DriverVerificationPresenter;
use App\Modules\Geo\Services\NearbyDriverIndex;
use App\Support\Exceptions\DomainException;
use App\Support\Geo\Point;
use Illuminate\Support\Facades\DB;

final readonly class SetDriverOnline
{
    public function __construct(
        private NearbyDriverIndex $index,
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

        $vehicleId ??= $driver->current_vehicle_id;
        $vehicleId ??= $driver->vehicles()
            ->where('is_active', true)
            ->value('id');
        if (! $vehicleId) {
            throw new class('No active vehicle on file.') extends DomainException
            {
                public function code(): string
                {
                    return 'driver.no_active_vehicle';
                }

                public function status(): int
                {
                    return 409;
                }
            };
        }

        DB::transaction(function () use ($driver, $vehicleId): void {
            $driver->update([
                'online' => true,
                'online_since' => now(),
                'current_vehicle_id' => $vehicleId,
            ]);
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
}
