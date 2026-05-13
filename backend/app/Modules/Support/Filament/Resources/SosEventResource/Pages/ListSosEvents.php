<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\SosEventResource\Pages;

use App\Modules\Support\Filament\Resources\SosEventResource;
use Filament\Resources\Pages\ListRecords;

final class ListSosEvents extends ListRecords
{
    protected static string $resource = SosEventResource::class;
}
