<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\DriverResource\Pages;

use App\Modules\Driver\Filament\Resources\DriverResource;
use Filament\Resources\Pages\ListRecords;

final class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;
}
