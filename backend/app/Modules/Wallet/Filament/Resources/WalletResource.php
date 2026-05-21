<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Filament\Resources;

use App\Modules\Wallet\Filament\Resources\WalletResource\Pages;
use App\Modules\Wallet\Models\Wallet;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('user.phone_e164')->label('User'),
                Tables\Columns\TextColumn::make('user.type')->badge(),
                Tables\Columns\TextColumn::make('currency')->badge(),
                Tables\Columns\TextColumn::make('balance_cached')
                    ->label('Balance')
                    ->money(fn ($record) => $record->currency)
                    ->sortable()
                    ->color(fn ($record) => ((float) $record->balance_cached) < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('held_amount')
                    ->label('Held')
                    ->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\Filter::make('negative_balance')
                    ->label('Negative balance')
                    ->query(fn ($query) => $query->where('balance_cached', '<', 0)),
                Tables\Filters\Filter::make('held')
                    ->label('Has hold')
                    ->query(fn ($query) => $query->where('held_amount', '>', 0)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('balance_cached', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWallets::route('/'),
        ];
    }
}
