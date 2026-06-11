<?php

namespace App\Filament\Resources\FundingApplicationResource\Pages;

use App\Filament\Resources\FundingApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFundingApplication extends EditRecord
{
    protected static string $resource = FundingApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
