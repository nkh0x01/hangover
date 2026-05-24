<?php

declare(strict_types=1);

namespace App\Filament\Resources\FundingProgramResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'მოთხოვნები / Rules';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('rule_type')->label('ტიპი')->options([
                'sector' => 'სექტორი',
                'region' => 'რეგიონი',
                'business_age_min_months' => 'ბიზნესის ხნოვანება (მინ. თვე)',
                'business_age_max_months' => 'ბიზნესის ხნოვანება (მაქს. თვე)',
                'revenue_min_gel' => 'ბრუნვა (მინ. GEL)',
                'revenue_max_gel' => 'ბრუნვა (მაქს. GEL)',
                'employees_min' => 'თანამშრომლები (მინ.)',
                'employees_max' => 'თანამშრომლები (მაქს.)',
                'amount_min_gel' => 'თანხა (მინ. GEL)',
                'amount_max_gel' => 'თანხა (მაქს. GEL)',
                'co_financing_max_pct' => 'თანადაფინანსება %',
                'requires_woman_owned' => 'ქალის მფლობელობა',
                'requires_youth_owned' => 'ახალგაზრდის ბიზნესი',
                'requires_mountainous' => 'მაღალმთიანი რეგიონი',
                'requires_startup' => 'სტარტაპი',
                'requires_existing_business' => 'არსებული ბიზნესი',
                'requires_agriculture' => 'სოფლის მეურნეობა',
                'requires_non_agriculture' => 'არა-აგრო',
                'purpose' => 'მიზანი',
            ])->required(),
            Forms\Components\KeyValue::make('criteria')->label('კრიტერიუმი (JSON)')
                ->helperText('მაგ.: {"in":["crafts","textile"]}, {"min":12,"max":60}, {"value":true}')
                ->required(),
            Forms\Components\TextInput::make('weight')->label('წონა')->numeric()->default(10),
            Forms\Components\Toggle::make('is_required')->label('სავალდებულო'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rule_type')->label('ტიპი'),
                Tables\Columns\TextColumn::make('criteria')->label('კრიტერიუმი')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : $state),
                Tables\Columns\TextColumn::make('weight')->label('წონა'),
                Tables\Columns\IconColumn::make('is_required')->label('სავალდებულო')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('მოთხოვნის დამატება'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
