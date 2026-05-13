<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Filament\Resources;

use App\Modules\Wallet\Filament\Resources\TransactionResource\Pages;
use App\Modules\Wallet\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Transactions';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ulid')->label('Ref')->copyable()->limit(10),
                Tables\Columns\TextColumn::make('wallet.user.phone_e164')->label('User'),
                Tables\Columns\TextColumn::make('kind')->badge(),
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state) => $state === 'credit' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('amount')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('balance_after')->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('description')->limit(40)->tooltip(fn ($record) => $record->description),
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')->options([
                    'topup' => 'Topup',
                    'ride_charge' => 'Ride charge',
                    'ride_payout' => 'Ride payout',
                    'refund' => 'Refund',
                    'promo_credit' => 'Promo credit',
                    'referral_bonus' => 'Referral bonus',
                    'withdrawal' => 'Withdrawal',
                    'adjustment' => 'Adjustment',
                    'hold' => 'Hold',
                    'release' => 'Release',
                ]),
                Tables\Filters\SelectFilter::make('direction')->options([
                    'credit' => 'Credit',
                    'debit' => 'Debit',
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
