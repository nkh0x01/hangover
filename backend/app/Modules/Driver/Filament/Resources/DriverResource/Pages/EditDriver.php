<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\DriverResource\Pages;

use App\Modules\Driver\Filament\Resources\DriverResource;
use Filament\Resources\Pages\EditRecord;

final class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;
}
