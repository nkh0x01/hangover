<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Filament\Resources\UserResource\Pages;
use App\Modules\Identity\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('first_name')->maxLength(80),
            Forms\Components\TextInput::make('last_name')->maxLength(80),
            Forms\Components\TextInput::make('phone_e164')->label('Phone')->disabled(),
            Forms\Components\TextInput::make('email')->email(),
            Forms\Components\Select::make('type')
                ->options(['customer' => 'Customer', 'driver' => 'Driver', 'admin' => 'Admin', 'dispatcher' => 'Dispatcher'])
                ->required()
                ->disabled(),
            Forms\Components\Select::make('status')
                ->options(['active' => 'Active', 'suspended' => 'Suspended', 'banned' => 'Banned'])
                ->required(),
            Forms\Components\Select::make('locale')
                ->options(['ka' => 'Georgian', 'en' => 'English', 'ru' => 'Russian']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('ulid')->copyable()->limit(10),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('first_name'),
                Tables\Columns\TextColumn::make('last_name'),
                Tables\Columns\TextColumn::make('phone_e164')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'suspended',
                        'danger' => 'banned',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'customer' => 'Customer',
                    'driver' => 'Driver',
                    'admin' => 'Admin',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'suspended' => 'Suspended',
                    'banned' => 'Banned',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
