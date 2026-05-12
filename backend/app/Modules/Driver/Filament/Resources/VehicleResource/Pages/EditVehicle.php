<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\VehicleResource\Pages;

use App\Modules\Driver\Filament\Resources\VehicleResource;
use Filament\Resources\Pages\EditRecord;

final class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;
}
