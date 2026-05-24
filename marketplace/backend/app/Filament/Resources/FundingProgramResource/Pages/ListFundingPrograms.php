<?php

namespace App\Filament\Resources\FundingProgramResource\Pages;

use App\Filament\Resources\FundingProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFundingPrograms extends ListRecords
{
    protected static string $resource = FundingProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
