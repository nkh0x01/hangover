<?php

declare(strict_types=1);

namespace App\Modules\Riding\Filament\Pages;

use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

final class LiveRidesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'მგზავრობები';

    protected static ?string $navigationLabel = 'აქტიური მგზავრობები';

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static string $view = 'filament.pages.live-rides';

    protected static ?string $title = 'აქტიური მგზავრობები';

    protected static ?int $navigationSort = 1;

    /**
     * Filament auto-polls the page every 5s — this is the closest we
     * get to a real live monitor without wiring Echo into the admin
     * panel. Phase 4 swaps this for a Reverb subscription.
     */
    public ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ride::query()->whereIn('status', [
                    RideStatus::Requested->value,
                    RideStatus::Searching->value,
                    RideStatus::Offered->value,
                    RideStatus::Accepted->value,
                    RideStatus::DriverArriving->value,
                    RideStatus::DriverArrived->value,
                    RideStatus::InProgress->value,
                ])->latest('requested_at'),
            )
            ->poll($this->pollingInterval)
            ->columns([
                Tables\Columns\TextColumn::make('ulid')->label('Ride')->copyable()->limit(10),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('customer.phone_e164')->label('Customer'),
                Tables\Columns\TextColumn::make('driver.user.phone_e164')->label('Driver'),
                Tables\Columns\TextColumn::make('pickup_address')->limit(30),
                Tables\Columns\TextColumn::make('dropoff_address')->limit(30),
                Tables\Columns\TextColumn::make('quoted_amount')->money(fn ($record) => $record->currency),
                Tables\Columns\TextColumn::make('requested_at')->dateTime()->since(),
            ]);
    }
}
