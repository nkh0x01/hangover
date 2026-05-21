<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources;

use App\Modules\Driver\Filament\Resources\VehicleResource\Pages;
use App\Modules\Driver\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Drivers';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('driver_id')->relationship('driver', 'id')->required(),
            Forms\Components\Select::make('type')->options([
                'scooter_electric' => 'Scooter (electric)',
                'scooter_petrol' => 'Scooter (petrol)',
                'moped' => 'Moped',
                'bicycle_electric' => 'E-bike',
            ])->required(),
            Forms\Components\TextInput::make('brand')->required()->maxLength(60),
            Forms\Components\TextInput::make('model')->required()->maxLength(60),
            Forms\Components\TextInput::make('plate')->required()->maxLength(20),
            Forms\Components\TextInput::make('color')->maxLength(30),
            Forms\Components\TextInput::make('year')->numeric(),
            Forms\Components\Toggle::make('is_active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('driver_id'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('brand'),
                Tables\Columns\TextColumn::make('model'),
                Tables\Columns\TextColumn::make('plate')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
