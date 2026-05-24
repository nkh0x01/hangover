<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\DriverApplicationResource\Pages;

use App\Modules\Driver\Filament\Resources\DriverApplicationResource;
use Filament\Resources\Pages\EditRecord;

final class EditDriverApplication extends EditRecord
{
    protected static string $resource = DriverApplicationResource::class;
}
