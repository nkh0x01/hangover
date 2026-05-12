<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\VehicleResource\Pages;

use App\Modules\Driver\Filament\Resources\VehicleResource;
use Filament\Resources\Pages\ListRecords;

final class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;
}
