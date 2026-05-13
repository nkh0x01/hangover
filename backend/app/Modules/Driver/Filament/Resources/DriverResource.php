<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources;

use App\Modules\Driver\Filament\Resources\DriverResource\Pages;
use App\Modules\Driver\Models\Driver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Drivers';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'phone_e164')
                ->disabled(),
            Forms\Components\Select::make('city_id')
                ->relationship('city', 'name')
                ->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'in_review' => 'In Review',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'suspended' => 'Suspended',
                ])
                ->required(),
            Forms\Components\Textarea::make('approval_notes')->rows(3)->columnSpanFull(),
            Forms\Components\Toggle::make('online')->disabled(),
            Forms\Components\TextInput::make('rating_avg')->numeric()->disabled(),
            Forms\Components\TextInput::make('trips_completed')->numeric()->disabled(),
            Forms\Components\TextInput::make('commission_rate_override')->numeric()->step(0.0001),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.first_name')->label('Name'),
                Tables\Columns\TextColumn::make('user.phone_e164')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->colors([
                        'warning' => fn ($state) => in_array($state, ['pending', 'in_review'], true),
                        'success' => 'approved',
                        'danger' => fn ($state) => in_array($state, ['rejected', 'suspended'], true),
                    ]),
                Tables\Columns\TextColumn::make('verification_status')
                    ->label('Verification')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'verified' => 'success',
                        'in_review' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('online')->boolean(),
                Tables\Columns\TextColumn::make('rating_avg')->sortable(),
                Tables\Columns\TextColumn::make('trips_completed')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'in_review' => 'In Review',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'suspended' => 'Suspended',
                ]),
                Tables\Filters\SelectFilter::make('verification_status')->options([
                    'pending' => 'Pending',
                    'in_review' => 'In review',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                ]),
                Tables\Filters\TernaryFilter::make('online'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'view' => Pages\ViewDriver::route('/{record}'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
