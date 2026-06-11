<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FundingApplicationResource\Pages;
use App\Filament\Resources\FundingApplicationResource\RelationManagers;
use App\Modules\Financing\Models\FundingApplication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FundingApplicationResource extends Resource
{
    protected static ?string $model = FundingApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'დაფინანსება';

    protected static ?string $modelLabel = 'განაცხადი';

    protected static ?string $pluralModelLabel = 'განაცხადები';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\Select::make('seller_id')
                    ->relationship('seller', 'id'),
                Forms\Components\TextInput::make('funding_program_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\Textarea::make('business_profile_snapshot')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('amount_requested_gel')
                    ->numeric(),
                Forms\Components\Textarea::make('purpose_ka')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('assigned_consultant_id')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('submitted_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('seller.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('funding_program_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_requested_gel')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assigned_consultant_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFundingApplications::route('/'),
            'create' => Pages\CreateFundingApplication::route('/create'),
            'edit' => Pages\EditFundingApplication::route('/{record}/edit'),
        ];
    }
}
