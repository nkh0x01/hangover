<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Modules\Admin\Models\AdminAction;
use App\Modules\Seller\Models\Seller;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'მაღაზია';

    protected static ?string $modelLabel = 'მაღაზია';

    protected static ?string $pluralModelLabel = 'მაღაზიები';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        return (string) Seller::query()->where('verification_status', 'pending')->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('ბიზნესის ინფორმაცია')->schema([
                Forms\Components\Select::make('user_id')->label('მფლობელი')->relationship('user', 'name')->required(),
                Forms\Components\TextInput::make('business_name')->label('მაღაზიის სახელი')->required(),
                Forms\Components\TextInput::make('slug')->label('Slug')->required(),
                Forms\Components\Select::make('legal_form')->label('სამართლებრივი ფორმა')
                    ->options(config('marketplace.legal_forms'))->required(),
                Forms\Components\TextInput::make('tax_id')->label('საგადასახადო ნომერი'),
                Forms\Components\Select::make('sector')->label('სექტორი')
                    ->options(config('marketplace.seller_sectors'))->required(),
                Forms\Components\Select::make('region')->label('რეგიონი')
                    ->options(config('marketplace.regions'))->required(),
                Forms\Components\TextInput::make('municipality')->label('მუნიციპალიტეტი'),
            ])->columns(2),

            Forms\Components\Section::make('ბიზნესის მახასიათებლები')->schema([
                Forms\Components\TextInput::make('business_age_months')->label('ბიზნესის ხნოვანება (თვე)')->numeric()->default(0),
                Forms\Components\TextInput::make('annual_revenue_gel')->label('წლიური ბრუნვა (GEL)')->numeric(),
                Forms\Components\TextInput::make('employees_count')->label('თანამშრომელთა რაოდენობა')->numeric()->default(0),
                Forms\Components\Toggle::make('is_woman_owned')->label('ქალის მფლობელობა'),
                Forms\Components\Toggle::make('is_youth_owned')->label('ახალგაზრდის ბიზნესი (35-მდე)'),
                Forms\Components\Toggle::make('is_mountainous_region')->label('მაღალმთიანი რეგიონი'),
                Forms\Components\Toggle::make('is_startup')->label('სტარტაპი'),
                Forms\Components\Toggle::make('is_agriculture')->label('სოფლის მეურნეობა'),
                Forms\Components\Toggle::make('is_made_in_georgia_verified')->label('„ქართული წარმოება" დადასტურებული'),
            ])->columns(2),

            Forms\Components\Section::make('მაღაზიის გვერდი')->schema([
                Forms\Components\Textarea::make('story')->label('მეწარმის ისტორია')->columnSpanFull(),
                Forms\Components\TextInput::make('website_url')->label('ვებსაიტი')->url(),
                Forms\Components\TextInput::make('facebook_url')->label('Facebook')->url(),
                Forms\Components\TextInput::make('instagram_url')->label('Instagram')->url(),
            ])->columns(2),

            Forms\Components\Section::make('ვერიფიკაცია')->schema([
                Forms\Components\Select::make('verification_status')->label('ვერიფიკაციის სტატუსი')->options([
                    'pending' => 'მოლოდინში',
                    'submitted' => 'წარდგენილი',
                    'approved' => 'დადასტურებული',
                    'rejected' => 'უარყოფილი',
                    'suspended' => 'შეჩერებული',
                ])->required(),
                Forms\Components\DateTimePicker::make('verified_at')->label('დადასტურების თარიღი'),
                Forms\Components\Textarea::make('rejection_reason')->label('უარყოფის მიზეზი')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->label('მაღაზია')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('მფლობელი')->searchable(),
                Tables\Columns\TextColumn::make('sector')->label('სექტორი')
                    ->formatStateUsing(fn ($state) => config("marketplace.seller_sectors.{$state}", $state)),
                Tables\Columns\TextColumn::make('region')->label('რეგიონი')
                    ->formatStateUsing(fn ($state) => config("marketplace.regions.{$state}", $state)),
                Tables\Columns\TextColumn::make('verification_status')->label('სტატუსი')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'pending', 'submitted' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'მოლოდინში',
                        'submitted' => 'წარდგენილი',
                        'approved' => 'დადასტურებული',
                        'rejected' => 'უარყოფილი',
                        'suspended' => 'შეჩერებული',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_woman_owned')->label('ქალის')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('შექმნა')->dateTime('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('verification_status')->label('სტატუსი')->options([
                    'pending' => 'მოლოდინში',
                    'submitted' => 'წარდგენილი',
                    'approved' => 'დადასტურებული',
                    'rejected' => 'უარყოფილი',
                ]),
                SelectFilter::make('sector')->label('სექტორი')->options(config('marketplace.seller_sectors')),
                SelectFilter::make('region')->label('რეგიონი')->options(config('marketplace.regions')),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('დადასტურება')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (Seller $r) => in_array($r->verification_status, ['pending', 'submitted'], true))
                    ->requiresConfirmation()
                    ->action(function (Seller $record) {
                        $record->update([
                            'verification_status' => 'approved',
                            'verified_at' => now(),
                            'is_made_in_georgia_verified' => true,
                        ]);
                        $record->user->assignRole('seller');
                        AdminAction::create([
                            'admin_id' => auth()->id(),
                            'subject_type' => Seller::class,
                            'subject_id' => $record->id,
                            'action' => 'seller.approve',
                            'created_at' => now(),
                        ]);
                        Notification::make()->title('მაღაზია დადასტურდა')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('უარყოფა')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Seller $r) => in_array($r->verification_status, ['pending', 'submitted'], true))
                    ->form([Forms\Components\Textarea::make('reason')->label('მიზეზი')->required()])
                    ->action(function (Seller $record, array $data) {
                        $record->update([
                            'verification_status' => 'rejected',
                            'rejection_reason' => $data['reason'],
                        ]);
                        AdminAction::create([
                            'admin_id' => auth()->id(),
                            'subject_type' => Seller::class,
                            'subject_id' => $record->id,
                            'action' => 'seller.reject',
                            'payload' => ['reason' => $data['reason']],
                            'created_at' => now(),
                        ]);
                        Notification::make()->title('მაღაზია უარყოფილია')->warning()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
