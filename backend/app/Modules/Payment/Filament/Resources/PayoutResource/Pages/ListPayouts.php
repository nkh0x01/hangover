<?php

declare(strict_types=1);

namespace App\Modules\Payment\Filament\Resources\PayoutResource\Pages;

use App\Modules\Payment\Filament\Resources\PayoutResource;
use Filament\Resources\Pages\ListRecords;

final class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;
}
