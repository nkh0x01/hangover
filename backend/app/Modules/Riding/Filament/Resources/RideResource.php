<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Resources;

use App\Modules\Riding\Filament\Resources\RideResource\Pages;
use App\Modules\Riding\Models\Ride;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Rides';

    protected static ?int $navigationSort = 40;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ulid')->copyable()->limit(10),
                Tables\Columns\TextColumn::make('customer.phone_e164')->label('Customer'),
                Tables\Columns\TextColumn::make('driver.user.phone_e164')->label('Driver'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'success' => 'completed',
                    'warning' => fn ($state) => str($state)->is(['searching', 'offered', 'accepted', 'driver_arriving', 'driver_arrived', 'in_progress']),
                    'danger' => fn ($state) => in_array($state, ['cancelled', 'no_drivers', 'failed'], true),
                ]),
                Tables\Columns\TextColumn::make('quoted_amount')->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('final_amount')->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('requested_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'requested' => 'Requested',
                    'searching' => 'Searching',
                    'offered' => 'Offered',
                    'accepted' => 'Accepted',
                    'driver_arriving' => 'Driver arriving',
                    'driver_arrived' => 'Driver arrived',
                    'in_progress' => 'In progress',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                    'no_drivers' => 'No drivers',
                    'failed' => 'Failed',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('requested_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRides::route('/'),
            'view' => Pages\ViewRide::route('/{record}'),
        ];
    }
}
