<?php

declare(strict_types=1);

namespace App\Modules\Payment\Filament\Resources\PaymentResource\Pages;

use App\Modules\Payment\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

final class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
