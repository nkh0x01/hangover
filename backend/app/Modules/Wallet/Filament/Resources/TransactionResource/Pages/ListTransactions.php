<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Filament\Resources\TransactionResource\Pages;

use App\Modules\Wallet\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\ListRecords;

final class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;
}
