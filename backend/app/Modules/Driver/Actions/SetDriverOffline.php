<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Services\NearbyDriverIndex;
use Illuminate\Support\Facades\DB;

final readonly class SetDriverOffline
{
    public function __construct(private NearbyDriverIndex $index) {}

    public function execute(Driver $driver): Driver
    {
        DB::transaction(function () use ($driver): void {
            $lockedDriver = Driver::query()
                ->whereKey($driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDriver->update([
                'online' => false,
                'online_since' => null,
            ]);

            $activeShift = $lockedDriver->shifts()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->latest('started_at')
                ->first();

            $activeShift?->update(['ended_at' => now()]);
        });

        $this->index->remove($driver->city_id, $driver->id);

        return $driver->refresh();
    }
}
