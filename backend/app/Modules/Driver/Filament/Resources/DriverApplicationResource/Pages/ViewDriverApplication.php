<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources\DriverApplicationResource\Pages;

use App\Modules\Driver\Filament\Resources\DriverApplicationResource;
use App\Modules\Driver\Models\DriverApplication;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

final class ViewDriverApplication extends ViewRecord
{
    protected static string $resource = DriverApplicationResource::class;

    /**
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('რედაქტირება'),
            Actions\Action::make('approve')
                ->label('დამტკიცება')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof DriverApplication && $this->record->status !== 'approved')
                ->action(fn (): ?DriverApplication => DriverApplicationResource::approveWithNotification($this->record)),
            Actions\Action::make('needs_changes')
                ->label('ცვლილებების მოთხოვნა')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $this->record instanceof DriverApplication && ! in_array($this->record->status, ['approved', 'rejected'], true))
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('რა უნდა შეცვალოს მძღოლმა?')
                        ->required()
                        ->rows(4),
                ])
                ->action(fn (array $data): bool => DriverApplicationResource::requestChanges($this->record, (string) $data['note'])),
            Actions\Action::make('reject')
                ->label('უარყოფა')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record instanceof DriverApplication && $this->record->status !== 'approved')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('უარყოფის მიზეზი')
                        ->required()
                        ->rows(4),
                ])
                ->action(fn (array $data): bool => DriverApplicationResource::reject($this->record, (string) $data['reason'])),
        ];
    }
}
