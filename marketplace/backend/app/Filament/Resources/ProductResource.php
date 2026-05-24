<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Modules\Admin\Models\AdminAction;
use App\Modules\Catalog\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'მაღაზია';

    protected static ?string $modelLabel = 'პროდუქტი';

    protected static ?string $pluralModelLabel = 'პროდუქტები';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        return (string) Product::query()->where('status', 'pending')->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('ძირითადი ინფორმაცია')->schema([
                Forms\Components\Select::make('seller_id')->label('გამყიდველი')->relationship('seller', 'business_name')->required(),
                Forms\Components\Select::make('category_id')->label('კატეგორია')->relationship('category', 'name_ka')->required(),
                Forms\Components\TextInput::make('title_ka')->label('სათაური (ქართულად)')->required(),
                Forms\Components\TextInput::make('slug')->label('Slug')->required(),
                Forms\Components\Textarea::make('description_ka')->label('აღწერა (ქართულად)')->required()->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('ფასი და მარაგი')->schema([
                Forms\Components\TextInput::make('price_gel')->label('ფასი (GEL)')->numeric()->required(),
                Forms\Components\TextInput::make('compare_at_price_gel')->label('ფასი ფასდაკლებამდე (GEL)')->numeric(),
                Forms\Components\TextInput::make('stock')->label('მარაგი')->numeric()->default(0),
                Forms\Components\Toggle::make('is_made_to_order')->label('შეკვეთით'),
                Forms\Components\TextInput::make('lead_time_days')->label('წარმოების დრო (დღე)')->numeric(),
                Forms\Components\Select::make('production_type')->label('წარმოების ტიპი')->options(config('marketplace.production_types')),
            ])->columns(2),

            Forms\Components\Section::make('სტატუსი')->schema([
                Forms\Components\Select::make('status')->label('სტატუსი')->options([
                    'draft' => 'დრაფტი',
                    'pending' => 'მოლოდინში',
                    'published' => 'გამოქვეყნებული',
                    'archived' => 'არქივი',
                    'rejected' => 'უარყოფილი',
                ])->required(),
                Forms\Components\DateTimePicker::make('published_at')->label('გამოქვეყნების თარიღი'),
                Forms\Components\Textarea::make('rejection_reason')->label('უარყოფის მიზეზი')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_ka')->label('სათაური')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('seller.business_name')->label('გამყიდველი')->searchable(),
                Tables\Columns\TextColumn::make('category.name_ka')->label('კატეგორია'),
                Tables\Columns\TextColumn::make('price_gel')->label('ფასი')->money('GEL', 0)->sortable(),
                Tables\Columns\TextColumn::make('stock')->label('მარაგი')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('სტატუსი')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => 'დრაფტი',
                        'pending' => 'მოლოდინში',
                        'published' => 'გამოქვეყნებული',
                        'archived' => 'არქივი',
                        'rejected' => 'უარყოფილი',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('production_type')->label('წარმოება')
                    ->formatStateUsing(fn ($state) => config("marketplace.production_types.{$state}", $state)),
            ])
            ->filters([
                SelectFilter::make('status')->label('სტატუსი')->options([
                    'draft' => 'დრაფტი',
                    'pending' => 'მოლოდინში',
                    'published' => 'გამოქვეყნებული',
                    'rejected' => 'უარყოფილი',
                ]),
                SelectFilter::make('category_id')->label('კატეგორია')->relationship('category', 'name_ka'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('გამოქვეყნება')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Product $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Product $record) {
                        $record->update(['status' => 'published', 'published_at' => now()]);
                        AdminAction::create([
                            'admin_id' => auth()->id(),
                            'subject_type' => Product::class,
                            'subject_id' => $record->id,
                            'action' => 'product.approve',
                            'created_at' => now(),
                        ]);
                        Notification::make()->title('პროდუქტი გამოქვეყნდა')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('უარყოფა')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Product $r) => $r->status === 'pending')
                    ->form([Forms\Components\Textarea::make('reason')->label('მიზეზი')->required()])
                    ->action(function (Product $record, array $data) {
                        $record->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);
                        AdminAction::create([
                            'admin_id' => auth()->id(),
                            'subject_type' => Product::class,
                            'subject_id' => $record->id,
                            'action' => 'product.reject',
                            'payload' => ['reason' => $data['reason']],
                            'created_at' => now(),
                        ]);
                        Notification::make()->title('პროდუქტი უარყოფილია')->warning()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
