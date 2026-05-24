<?php

declare(strict_types=1);

namespace App\Modules\Driver\Filament\Resources;

use App\Modules\Driver\Filament\Resources\DriverApplicationResource\Pages;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\DriverApplicationDocument;
use App\Modules\Driver\Models\DriverDocument;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

final class DriverApplicationResource extends Resource
{
    protected static ?string $model = DriverApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Drivers';

    protected static ?string $slug = 'driver-applications';

    protected static ?int $navigationSort = 19;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Application')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(self::statusOptions())
                        ->required(),
                    Forms\Components\Select::make('city_id')
                        ->relationship('city', 'name'),
                    Forms\Components\TextInput::make('first_name')->maxLength(80),
                    Forms\Components\TextInput::make('last_name')->maxLength(80),
                    Forms\Components\TextInput::make('personal_id')->maxLength(32),
                    Forms\Components\TextInput::make('phone_e164')->maxLength(32),
                    Forms\Components\TextInput::make('email')->email(),
                    Forms\Components\Select::make('driver_type')->options([
                        'moto' => 'Moto',
                        'car' => 'Car',
                        'courier' => 'Courier',
                    ]),
                ]),
            Forms\Components\Section::make('Vehicle')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('vehicle_type')->options([
                        'scooter_electric' => 'Scooter (electric)',
                        'scooter_petrol' => 'Scooter (petrol)',
                        'moped' => 'Moped',
                        'bicycle_electric' => 'E-bike',
                        'car' => 'Car',
                    ]),
                    Forms\Components\TextInput::make('vehicle_brand')->maxLength(60),
                    Forms\Components\TextInput::make('vehicle_model')->maxLength(60),
                    Forms\Components\TextInput::make('vehicle_year')->numeric(),
                    Forms\Components\TextInput::make('vehicle_color')->maxLength(30),
                    Forms\Components\TextInput::make('vehicle_plate')->maxLength(20),
                ]),
            Forms\Components\Section::make('Review')
                ->schema([
                    Forms\Components\Textarea::make('rejection_reason')->rows(3),
                    Forms\Components\Textarea::make('admin_note')->rows(3),
                    Forms\Components\Placeholder::make('documents')
                        ->content(fn (?DriverApplication $record): HtmlString => self::documentSummary($record)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.phone_e164')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('first_name')->searchable(),
                Tables\Columns\TextColumn::make('last_name')->searchable(),
                Tables\Columns\TextColumn::make('city.name')->label('City'),
                Tables\Columns\TextColumn::make('driver_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'submitted', 'pending', 'needs_changes' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->since(),
                Tables\Columns\TextColumn::make('reviewed_at')->dateTime()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DriverApplication $record): bool => $record->status !== 'approved')
                    ->action(fn (DriverApplication $record): DriverApplication => self::approve($record)),
                Tables\Actions\Action::make('needs_changes')
                    ->label('Request changes')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('note')->required()->rows(3),
                    ])
                    ->action(fn (DriverApplication $record, array $data): bool => $record->update([
                        'status' => 'needs_changes',
                        'rejection_reason' => $data['note'],
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => auth()->id(),
                    ])),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->rows(3),
                    ])
                    ->action(fn (DriverApplication $record, array $data): bool => $record->update([
                        'status' => 'rejected',
                        'rejection_reason' => $data['reason'],
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => auth()->id(),
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverApplications::route('/'),
            'view' => Pages\ViewDriverApplication::route('/{record}'),
            'edit' => Pages\EditDriverApplication::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'pending' => 'Pending review',
            'needs_changes' => 'Needs changes',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    }

    private static function approve(DriverApplication $application): DriverApplication
    {
        return DB::transaction(function () use ($application): DriverApplication {
            $cityId = $application->city_id
                ?? City::query()->where('is_active', true)->orderBy('id')->value('id');

            $driver = Driver::query()->firstOrCreate(
                ['user_id' => $application->user_id],
                [
                    'city_id' => $cityId,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by_user_id' => auth()->id(),
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ],
            );

            $driver->update([
                'city_id' => $application->city_id ?? $driver->city_id,
                'status' => 'approved',
                'approved_at' => $driver->approved_at ?? now(),
                'approved_by_user_id' => auth()->id(),
                'verification_status' => 'verified',
                'verified_at' => $driver->verified_at ?? now(),
                'verification_notes' => null,
            ]);

            $vehicle = Vehicle::query()->create([
                'driver_id' => $driver->id,
                'type' => self::supportedVehicleType($application->vehicle_type),
                'brand' => $application->vehicle_brand ?: 'Unknown',
                'model' => $application->vehicle_model ?: 'Unknown',
                'plate' => $application->vehicle_plate ?: 'NO-PLATE-'.$application->id,
                'color' => $application->vehicle_color,
                'year' => $application->vehicle_year,
                'is_active' => true,
                'verified_at' => now(),
                'verified_by_user_id' => auth()->id(),
            ]);

            $driver->update(['current_vehicle_id' => $vehicle->id]);

            foreach ($application->documents as $document) {
                $mappedType = self::driverDocumentType($document->doc_type);
                if ($mappedType === null) {
                    continue;
                }

                DriverDocument::query()->updateOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'doc_type' => $mappedType,
                    ],
                    [
                        'file_path' => $document->file_path,
                        'file_sha256' => $document->file_sha256,
                        'status' => 'approved',
                        'reviewed_at' => now(),
                        'reviewed_by_user_id' => auth()->id(),
                    ],
                );
            }

            $application->update([
                'status' => 'approved',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => auth()->id(),
                'rejection_reason' => null,
            ]);

            return $application->refresh();
        });
    }

    private static function driverDocumentType(string $applicationType): ?string
    {
        return match ($applicationType) {
            'id_front',
            'id_back',
            'license_front',
            'license_back',
            'vehicle_registration',
            'insurance' => $applicationType,
            'selfie' => 'selfie_with_id',
            default => null,
        };
    }

    private static function supportedVehicleType(?string $vehicleType): string
    {
        return in_array($vehicleType, ['scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric'], true)
            ? $vehicleType
            : 'moped';
    }

    private static function documentSummary(?DriverApplication $application): HtmlString
    {
        if ($application === null) {
            return new HtmlString('Save the application before documents can appear.');
        }

        $application->loadMissing('documents');

        if ($application->documents->isEmpty()) {
            return new HtmlString('No uploaded documents yet.');
        }

        $items = $application->documents
            ->map(function (DriverApplicationDocument $document): string {
                $url = self::documentUrl($document);
                $label = e(sprintf('%s [%s]', $document->doc_type, $document->status));
                $path = e($document->file_path);

                if ($url === null) {
                    return "<li>{$label}<br><code>{$path}</code></li>";
                }

                return sprintf(
                    '<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a><br><code>%s</code></li>',
                    e($url),
                    $label,
                    $path,
                );
            })
            ->implode('');

        return new HtmlString("<ul>{$items}</ul>");
    }

    private static function documentUrl(DriverApplicationDocument $document): ?string
    {
        $disk = (string) config('drivers.docs_disk', config('filesystems.default', 'local'));

        try {
            return Storage::disk($disk)->temporaryUrl($document->file_path, now()->addMinutes(15));
        } catch (\Throwable) {
            try {
                return Storage::disk($disk)->url($document->file_path);
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
