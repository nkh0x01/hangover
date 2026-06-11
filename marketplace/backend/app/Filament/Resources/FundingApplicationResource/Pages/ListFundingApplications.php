<?php

namespace App\Filament\Resources\FundingApplicationResource\Pages;

use App\Filament\Resources\FundingApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFundingApplications extends ListRecords
{
    protected static string $resource = FundingApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
