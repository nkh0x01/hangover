<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Filament\Resources\WalletResource\Pages;

use App\Modules\Wallet\Filament\Resources\WalletResource;
use Filament\Resources\Pages\ListRecords;

final class ListWallets extends ListRecords
{
    protected static string $resource = WalletResource::class;
}
