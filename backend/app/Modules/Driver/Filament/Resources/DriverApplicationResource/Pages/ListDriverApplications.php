<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\DriverApplicationResource\Pages;

use App\Modules\Driver\Filament\Resources\DriverApplicationResource;
use Filament\Resources\Pages\ListRecords;

final class ListDriverApplications extends ListRecords
{
    protected static string $resource = DriverApplicationResource::class;
}
