<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Services\NearbyDriverIndex;

final readonly class SetDriverOffline
{
    public function __construct(private NearbyDriverIndex $index) {}

    public function execute(Driver $driver): Driver
    {
        $driver->update(['online' => false]);
        $this->index->remove($driver->city_id, $driver->id);

        return $driver->refresh();
    }
}
