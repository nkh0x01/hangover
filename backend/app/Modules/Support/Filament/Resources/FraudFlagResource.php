<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources;

use App\Modules\Support\Actions\RaiseFraudFlag;
use App\Modules\Support\Actions\SuspendUser;
use App\Modules\Support\Filament\Resources\FraudFlagResource\Pages;
use App\Modules\Support\Models\FraudFlag;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class FraudFlagResource extends Resource
{
    protected static ?string $model = FraudFlag::class;

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Fraud flags';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.phone_e164')->label('Subject'),
                Tables\Columns\TextColumn::make('kind')->badge(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'block' => 'danger',
                        'warn' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('raised_by')->badge(),
                Tables\Columns\TextColumn::make('resolved_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')->options([
                    'info' => 'Info',
                    'warn' => 'Warn',
                    'block' => 'Block',
                ]),
                Tables\Filters\Filter::make('unresolved')
                    ->label('Unresolved only')
                    ->query(fn ($q) => $q->whereNull('resolved_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->visible(fn (FraudFlag $r): bool => $r->resolved_at === null)
                    ->form([
                        Forms\Components\Textarea::make('notes')->required()->rows(3),
                    ])
                    ->action(function (FraudFlag $record, array $data): void {
                        app(RaiseFraudFlag::class)->resolve($record, auth()->user(), $data['notes']);
                        Notification::make()->title('Flag resolved')->success()->send();
                    }),
                Tables\Actions\Action::make('suspend_user')
                    ->label('Suspend user')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (FraudFlag $r): bool => $r->user?->status === 'active')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (FraudFlag $record, array $data): void {
                        if ($record->user === null) {
                            return;
                        }
                        app(SuspendUser::class)->suspend($record->user, auth()->user(), $data['reason']);
                        Notification::make()->title('User suspended')->warning()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraudFlags::route('/'),
        ];
    }
}
