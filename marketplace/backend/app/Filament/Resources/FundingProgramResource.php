<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FundingProgramResource\Pages;
use App\Filament\Resources\FundingProgramResource\RelationManagers;
use App\Modules\Financing\Models\FundingProgram;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FundingProgramResource extends Resource
{
    protected static ?string $model = FundingProgram::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'დაფინანსება';

    protected static ?string $modelLabel = 'დაფინანსების პროგრამა';

    protected static ?string $pluralModelLabel = 'დაფინანსების პროგრამები';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('პროგრამის ინფორმაცია')->schema([
                Forms\Components\TextInput::make('name_ka')->label('სახელი (ქართულად)')->required(),
                Forms\Components\TextInput::make('slug')->label('Slug')->required(),
                Forms\Components\Select::make('provider')->label('მიმწოდებელი')->options([
                    'enterprise_georgia' => 'Enterprise Georgia',
                    'rda' => 'RDA — სოფლის მეურნეობის სააგენტო',
                    'gita' => 'GITA',
                    'grants_gov_ge' => 'grants.gov.ge',
                    'eu4business' => 'EU4Business',
                    'adb' => 'ADB',
                    'other' => 'სხვა',
                ])->required(),
                Forms\Components\Select::make('program_type')->label('პროგრამის ტიპი')->options([
                    'grant' => 'გრანტი',
                    'subsidized_loan' => 'შეღავათიანი სესხი',
                    'equity' => 'წილობრივი ინვესტიცია',
                    'guarantee' => 'გარანტია',
                    'training' => 'ტრენინგი',
                    'coaching' => 'კონსულტაცია',
                    'mixed' => 'შერეული',
                ])->required(),
                Forms\Components\Textarea::make('summary_ka')->label('მოკლე აღწერა')->required()->columnSpanFull(),
                Forms\Components\Textarea::make('description_ka')->label('სრული აღწერა')->required()->columnSpanFull()->rows(5),
            ])->columns(2),

            Forms\Components\Section::make('თანხა და პირობები')->schema([
                Forms\Components\TextInput::make('min_amount_gel')->label('მინ. თანხა (GEL)')->numeric(),
                Forms\Components\TextInput::make('max_amount_gel')->label('მაქს. თანხა (GEL)')->numeric(),
                Forms\Components\TextInput::make('co_financing_required_pct')->label('საჭირო თანადაფინანსება (%)')->numeric()->minValue(0)->maxValue(100),
                Forms\Components\TextInput::make('application_url')->label('ოფიციალური ბმული')->url(),
                Forms\Components\DatePicker::make('opens_at')->label('გახსნა'),
                Forms\Components\DatePicker::make('closes_at')->label('დახურვა'),
            ])->columns(2),

            Forms\Components\Section::make('სტატუსი')->schema([
                Forms\Components\Toggle::make('is_active')->label('აქტიური'),
                Forms\Components\Toggle::make('is_demo')->label('დემო ჩანაწერი')
                    ->helperText('გადართე როცა ოფიციალურად დადასტურდება მონაცემები'),
                Forms\Components\TextInput::make('contact_email')->label('კონტაქტი — ელფოსტა')->email(),
                Forms\Components\TextInput::make('contact_phone')->label('კონტაქტი — ტელ.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_ka')->label('სახელი')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('provider')->label('მიმწოდებელი')->badge(),
                Tables\Columns\TextColumn::make('program_type')->label('ტიპი')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'grant' => 'success',
                        'subsidized_loan' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'grant' => 'გრანტი',
                        'subsidized_loan' => 'შეღავათიანი სესხი',
                        'equity' => 'წილობრივი',
                        'guarantee' => 'გარანტია',
                        'training' => 'ტრენინგი',
                        'coaching' => 'კონსულტაცია',
                        'mixed' => 'შერეული',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('max_amount_gel')->label('მაქს. თანხა')->money('GEL', 0)->sortable(),
                Tables\Columns\TextColumn::make('closes_at')->label('დახურვა')->date('d.m.Y'),
                Tables\Columns\IconColumn::make('is_active')->label('აქტიური')->boolean(),
                Tables\Columns\IconColumn::make('is_demo')->label('დემო')->boolean(),
            ])
            ->filters([
                SelectFilter::make('provider')->label('მიმწოდებელი')->options([
                    'enterprise_georgia' => 'Enterprise Georgia',
                    'rda' => 'RDA',
                    'gita' => 'GITA',
                    'grants_gov_ge' => 'grants.gov.ge',
                    'eu4business' => 'EU4Business',
                    'other' => 'სხვა',
                ]),
                SelectFilter::make('program_type')->label('ტიპი')->options([
                    'grant' => 'გრანტი',
                    'subsidized_loan' => 'შეღავათიანი სესხი',
                    'training' => 'ტრენინგი',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFundingPrograms::route('/'),
            'create' => Pages\CreateFundingProgram::route('/create'),
            'edit' => Pages\EditFundingProgram::route('/{record}/edit'),
        ];
    }
}
