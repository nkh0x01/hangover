<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Resources\RideResource\Pages;

use App\Modules\Riding\Filament\Resources\RideResource;
use Filament\Resources\Pages\ListRecords;

final class ListRides extends ListRecords
{
    protected static string $resource = RideResource::class;
}
