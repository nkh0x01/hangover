<?php

declare(strict_types=1);

namespace App\Modules\Payment\Filament\Resources;

use App\Modules\Payment\Filament\Resources\PaymentResource\Pages;
use App\Modules\Payment\Models\Payment;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('ride.ulid')->label('Ride')->copyable()->limit(10),
                Tables\Columns\TextColumn::make('method')->badge(),
                Tables\Columns\TextColumn::make('provider')->badge(),
                Tables\Columns\TextColumn::make('amount')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'captured' => 'success',
                        'authorized', 'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded', 'partially_refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('failure_code')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('captured_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'authorized' => 'Authorized',
                    'captured' => 'Captured',
                    'failed' => 'Failed',
                    'refunded' => 'Refunded',
                    'partially_refunded' => 'Partially refunded',
                    'cancelled' => 'Cancelled',
                ]),
                Tables\Filters\SelectFilter::make('method')->options([
                    'cash' => 'Cash',
                    'card' => 'Card',
                    'wallet' => 'Wallet',
                    'apple_pay' => 'Apple Pay',
                    'google_pay' => 'Google Pay',
                ]),
                Tables\Filters\SelectFilter::make('provider')->options([
                    'cash' => 'Cash',
                    'wallet' => 'Wallet',
                    'stripe' => 'Stripe',
                    'bog' => 'BOG',
                    'tbc_pay' => 'TBC Pay',
                    'apple_pay' => 'Apple Pay',
                    'google_pay' => 'Google Pay',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
