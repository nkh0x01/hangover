<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\FraudFlagResource\Pages;

use App\Modules\Support\Filament\Resources\FraudFlagResource;
use Filament\Resources\Pages\ListRecords;

final class ListFraudFlags extends ListRecords
{
    protected static string $resource = FraudFlagResource::class;
}
