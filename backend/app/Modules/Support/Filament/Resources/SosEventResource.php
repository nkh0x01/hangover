<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources;

use App\Modules\Support\Actions\RaiseSosEvent;
use App\Modules\Support\Filament\Resources\SosEventResource\Pages;
use App\Modules\Support\Models\SosEvent;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class SosEventResource extends Resource
{
    protected static ?string $model = SosEvent::class;

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'SOS events';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.phone_e164')->label('Reporter'),
                Tables\Columns\TextColumn::make('ride.ulid')->label('Ride')->limit(10),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'acknowledged' => 'warning',
                        'resolved' => 'success',
                        'false_alarm' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('body')->limit(40),
                Tables\Columns\TextColumn::make('acknowledged_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('resolved_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'acknowledged' => 'Acknowledged',
                    'resolved' => 'Resolved',
                    'false_alarm' => 'False alarm',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->icon('heroicon-o-check')
                    ->visible(fn (SosEvent $r): bool => $r->status === 'open')
                    ->requiresConfirmation()
                    ->action(function (SosEvent $record): void {
                        app(RaiseSosEvent::class)->acknowledge($record, auth()->user());
                        Notification::make()->title('SOS acknowledged')->success()->send();
                    }),
                Tables\Actions\Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (SosEvent $r): bool => in_array($r->status, ['open', 'acknowledged'], true))
                    ->form([
                        Forms\Components\Select::make('resolution')
                            ->options([
                                'resolved' => 'Resolved (real event handled)',
                                'false_alarm' => 'False alarm',
                            ])
                            ->required(),
                    ])
                    ->action(function (SosEvent $record, array $data): void {
                        app(RaiseSosEvent::class)->resolve($record, auth()->user(), $data['resolution']);
                        Notification::make()->title('SOS resolved')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSosEvents::route('/'),
        ];
    }
}
