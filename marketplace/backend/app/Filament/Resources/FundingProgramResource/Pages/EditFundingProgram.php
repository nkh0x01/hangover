<?php

namespace App\Filament\Resources\FundingProgramResource\Pages;

use App\Filament\Resources\FundingProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFundingProgram extends EditRecord
{
    protected static string $resource = FundingProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
