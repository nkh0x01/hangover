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
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
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

    protected static ?string $navigationGroup = 'მძღოლები';

    protected static ?string $navigationLabel = 'მძღოლების განაცხადები';

    protected static ?string $modelLabel = 'მძღოლის განაცხადი';

    protected static ?string $pluralModelLabel = 'მძღოლების განაცხადები';

    protected static ?string $slug = 'driver-applications';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('განაცხადის სტატუსი')
                ->description('სტატუსი, განხილვის შენიშვნა და ადმინისტრატორის ბოლო გადაწყვეტილება.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('სტატუსი')
                        ->options(self::statusOptions())
                        ->required(),
                    Forms\Components\Select::make('city_id')
                        ->label('ქალაქი')
                        ->relationship('city', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('უარყოფის/ცვლილების მიზეზი')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('admin_note')
                        ->label('ადმინისტრატორის შიდა შენიშვნა')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('პირადი ინფორმაცია')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('first_name')->label('სახელი')->maxLength(80),
                    Forms\Components\TextInput::make('last_name')->label('გვარი')->maxLength(80),
                    Forms\Components\TextInput::make('personal_id')->label('პირადი ნომერი')->maxLength(32),
                    Forms\Components\TextInput::make('phone_e164')->label('ტელეფონი')->maxLength(32),
                    Forms\Components\TextInput::make('email')->label('ელფოსტა')->email(),
                    Forms\Components\DatePicker::make('birth_date')->label('დაბადების თარიღი'),
                    Forms\Components\TextInput::make('service_zone')->label('მომსახურების ზონა')->maxLength(80),
                    Forms\Components\Select::make('driver_type')
                        ->label('მძღოლის ტიპი')
                        ->options(self::driverTypeOptions()),
                ]),
            Forms\Components\Section::make('ტრანსპორტი')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('vehicle_type')
                        ->label('ტრანსპორტის ტიპი')
                        ->options(self::vehicleTypeOptions()),
                    Forms\Components\TextInput::make('vehicle_brand')->label('ბრენდი')->maxLength(60),
                    Forms\Components\TextInput::make('vehicle_model')->label('მოდელი')->maxLength(60),
                    Forms\Components\TextInput::make('vehicle_year')->label('წელი')->numeric(),
                    Forms\Components\TextInput::make('vehicle_color')->label('ფერი')->maxLength(30),
                    Forms\Components\TextInput::make('vehicle_plate')->label('სახელმწიფო ნომერი')->maxLength(20),
                    Forms\Components\TextInput::make('engine_cc')->label('ძრავის მოცულობა')->maxLength(20),
                    Forms\Components\DatePicker::make('insurance_expires_on')->label('დაზღვევის ვადა'),
                    Forms\Components\DatePicker::make('inspection_expires_on')->label('ტექ. ინსპექტირების ვადა'),
                ]),
            Forms\Components\Section::make('დადასტურებები და დოკუმენტები')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('information_confirmed')->label('ინფორმაცია დადასტურებულია'),
                    Forms\Components\Toggle::make('terms_accepted')->label('წესები მიღებულია'),
                    Forms\Components\Toggle::make('privacy_accepted')->label('მონაცემთა დამუშავება მიღებულია'),
                    Forms\Components\Placeholder::make('documents')
                        ->label('ატვირთული დოკუმენტები')
                        ->content(fn (?DriverApplication $record): HtmlString => self::documentSummary($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('განაცხადის მიმოხილვა')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->label('სტატუსი')
                        ->badge()
                        ->color(fn (string $state): string => self::statusColor($state))
                        ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? (string) $state),
                    Infolists\Components\TextEntry::make('submitted_at')->label('გაგზავნილია')->dateTime()->placeholder('არ არის გაგზავნილი'),
                    Infolists\Components\TextEntry::make('reviewed_at')->label('განხილულია')->dateTime()->placeholder('ჯერ არა'),
                    Infolists\Components\TextEntry::make('city.name')->label('ქალაქი')->placeholder('არ არის მითითებული'),
                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('ადმინისტრატორის მიზეზი/შენიშვნა')
                        ->placeholder('შენიშვნა არ არის')
                        ->columnSpanFull(),
                ]),
            Infolists\Components\Section::make('პირადი ინფორმაცია')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('first_name')->label('სახელი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('last_name')->label('გვარი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('personal_id')->label('პირადი ნომერი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('phone_e164')->label('ტელეფონი')->copyable()->placeholder('—'),
                    Infolists\Components\TextEntry::make('email')->label('ელფოსტა')->copyable()->placeholder('—'),
                    Infolists\Components\TextEntry::make('service_zone')->label('მომსახურების ზონა')->placeholder('—'),
                    Infolists\Components\TextEntry::make('driver_type')
                        ->label('მძღოლის ტიპი')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => self::driverTypeOptions()[$state] ?? (string) $state),
                ]),
            Infolists\Components\Section::make('ტრანსპორტი')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('vehicle_type')
                        ->label('ტიპი')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => self::vehicleTypeOptions()[$state] ?? (string) $state),
                    Infolists\Components\TextEntry::make('vehicle_brand')->label('ბრენდი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('vehicle_model')->label('მოდელი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('vehicle_year')->label('წელი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('vehicle_color')->label('ფერი')->placeholder('—'),
                    Infolists\Components\TextEntry::make('vehicle_plate')->label('სახელმწიფო ნომერი')->copyable()->placeholder('—'),
                ]),
            Infolists\Components\Section::make('დოკუმენტები და თანხმობები')
                ->columns(3)
                ->schema([
                    Infolists\Components\IconEntry::make('information_confirmed')->label('ინფორმაცია სწორია')->boolean(),
                    Infolists\Components\IconEntry::make('terms_accepted')->label('წესები მიღებულია')->boolean(),
                    Infolists\Components\IconEntry::make('privacy_accepted')->label('მონაცემთა თანხმობა')->boolean(),
                    Infolists\Components\RepeatableEntry::make('documents')
                        ->label('ატვირთული დოკუმენტები')
                        ->schema([
                            Infolists\Components\TextEntry::make('doc_type')
                                ->label('დოკუმენტი')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => self::documentTypeOptions()[$state] ?? (string) $state),
                            Infolists\Components\TextEntry::make('status')
                                ->label('სტატუსი')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    default => 'warning',
                                }),
                            Infolists\Components\TextEntry::make('file_path')
                                ->label('ფაილი')
                                ->limit(64)
                                ->copyable()
                                ->url(fn (DriverApplicationDocument $record): ?string => self::documentUrl($record), true),
                            Infolists\Components\TextEntry::make('created_at')->label('ატვირთულია')->dateTime(),
                        ])
                        ->columns(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('applicant')
                    ->label('განმცხადებელი')
                    ->state(fn (DriverApplication $record): string => trim(($record->first_name ?? '').' '.($record->last_name ?? '')) ?: 'სახელი არ არის')
                    ->description(fn (DriverApplication $record): ?string => $record->personal_id)
                    ->searchable(['first_name', 'last_name', 'personal_id']),
                Tables\Columns\TextColumn::make('phone_e164')
                    ->label('ტელეფონი')
                    ->copyable()
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('ქალაქი')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->label('ტრანსპორტი')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::vehicleTypeOptions()[$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('status')
                    ->label('სტატუსი')
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('დოკ.')
                    ->counts('documents')
                    ->badge()
                    ->color(fn (int $state): string => $state >= count(self::requiredDocumentTypes()) ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('გაგზავნილია')
                    ->dateTime()
                    ->since()
                    ->placeholder('მონახაზი'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('სტატუსი')
                    ->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('city_id')
                    ->label('ქალაქი')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('ნახვა'),
                Tables\Actions\EditAction::make()->label('რედაქტირება'),
                Tables\Actions\Action::make('approve')
                    ->label('დამტკიცება')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DriverApplication $record): bool => $record->status !== 'approved')
                    ->action(fn (DriverApplication $record): ?DriverApplication => self::approveWithNotification($record)),
                Tables\Actions\Action::make('needs_changes')
                    ->label('ცვლილებების მოთხოვნა')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (DriverApplication $record): bool => ! in_array($record->status, ['approved', 'rejected'], true))
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('რა უნდა შეცვალოს მძღოლმა?')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(fn (DriverApplication $record, array $data): bool => self::requestChanges($record, (string) $data['note'])),
                Tables\Actions\Action::make('reject')
                    ->label('უარყოფა')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DriverApplication $record): bool => $record->status !== 'approved')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('უარყოფის მიზეზი')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(fn (DriverApplication $record, array $data): bool => self::reject($record, (string) $data['reason'])),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->emptyStateHeading('მძღოლის განაცხადები ჯერ არ არის')
            ->emptyStateDescription('როცა მძღოლი აპიდან რეგისტრაციის ფორმას გაგზავნის, განაცხადი აქ გამოჩნდება.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
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
    public static function statusOptions(): array
    {
        return [
            'draft' => 'მონახაზი',
            'needs_completion' => 'შესავსებია',
            'submitted' => 'გაგზავნილი',
            'pending' => 'განხილვაშია',
            'manual_review' => 'ხელით შემოწმება',
            'needs_changes' => 'ცვლილებები საჭიროა',
            'approved' => 'დამტკიცებული',
            'rejected' => 'უარყოფილი',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function driverTypeOptions(): array
    {
        return [
            'moto' => 'მოტო მძღოლი',
            'car' => 'ავტომობილის მძღოლი',
            'courier' => 'კურიერი',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function vehicleTypeOptions(): array
    {
        return [
            'scooter_electric' => 'ელექტრო სკუტერი',
            'scooter_petrol' => 'ბენზინის სკუტერი',
            'moped' => 'მოპედი',
            'bicycle_electric' => 'ელექტრო ველოსიპედი',
            'car' => 'ავტომობილი',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function documentTypeOptions(): array
    {
        return [
            'id_front' => 'პირადობის წინა მხარე',
            'id_back' => 'პირადობის უკანა მხარე',
            'license_front' => 'მართვის მოწმობის წინა მხარე',
            'license_back' => 'მართვის მოწმობის უკანა მხარე',
            'vehicle_registration' => 'ტრანსპორტის რეგისტრაცია',
            'vehicle_photo' => 'ტრანსპორტის ფოტო',
            'selfie' => 'მძღოლის სელფი',
            'insurance' => 'დაზღვევა',
        ];
    }

    /**
     * @return list<string>
     */
    public static function approvalBlockers(DriverApplication $application): array
    {
        $application->loadMissing('documents');

        $missing = [];

        foreach (self::requiredFields() as $field => $label) {
            if (blank($application->{$field})) {
                $missing[] = $label;
            }
        }

        foreach (self::requiredConsents() as $field => $label) {
            if ($application->{$field} !== true) {
                $missing[] = $label;
            }
        }

        $uploadedTypes = $application->documents->pluck('doc_type')->all();
        foreach (self::requiredDocumentTypes() as $type) {
            if (! in_array($type, $uploadedTypes, true)) {
                $missing[] = self::documentTypeOptions()[$type] ?? $type;
            }
        }

        if (! array_key_exists((string) $application->vehicle_type, self::vehicleTypeOptions())) {
            $missing[] = 'ტრანსპორტის მხარდაჭერილი ტიპი';
        }

        return $missing;
    }

    public static function approveWithNotification(DriverApplication $application): ?DriverApplication
    {
        $blockers = self::approvalBlockers($application);

        if ($blockers !== []) {
            Notification::make()
                ->title('განაცხადის დამტკიცება ვერ მოხერხდა')
                ->body('აკლია: '.implode(', ', $blockers))
                ->danger()
                ->send();

            return null;
        }

        $application = self::approve($application);

        Notification::make()
            ->title('მძღოლის განაცხადი დამტკიცდა')
            ->body('მძღოლის პროფილი და ტრანსპორტი მზად არის.')
            ->success()
            ->send();

        return $application;
    }

    public static function requestChanges(DriverApplication $application, string $note): bool
    {
        $updated = $application->update([
            'status' => 'needs_changes',
            'rejection_reason' => $note,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => auth()->id(),
        ]);

        Notification::make()
            ->title('ცვლილებები მოთხოვნილია')
            ->body('მძღოლი აპში ნახავს ამ შენიშვნას.')
            ->warning()
            ->send();

        return $updated;
    }

    public static function reject(DriverApplication $application, string $reason): bool
    {
        $updated = $application->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => auth()->id(),
        ]);

        Notification::make()
            ->title('განაცხადი უარყოფილია')
            ->body('უარყოფის მიზეზი გამოჩნდება მძღოლის აპში.')
            ->danger()
            ->send();

        return $updated;
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

            $vehicle = $application->vehicle_id !== null
                ? Vehicle::query()->where('driver_id', $driver->id)->find($application->vehicle_id)
                : null;

            $vehiclePayload = [
                'driver_id' => $driver->id,
                'type' => self::supportedVehicleType($application->vehicle_type),
                'brand' => (string) $application->vehicle_brand,
                'model' => (string) $application->vehicle_model,
                'plate' => (string) $application->vehicle_plate,
                'color' => $application->vehicle_color,
                'year' => $application->vehicle_year,
                'is_active' => true,
                'verified_at' => now(),
                'verified_by_user_id' => auth()->id(),
            ];

            if ($vehicle instanceof Vehicle) {
                $vehicle->update($vehiclePayload);
            } else {
                $vehicle = Vehicle::query()->create($vehiclePayload);
            }

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
                'admin_note' => null,
            ]);

            return $application->refresh();
        });
    }

    /**
     * @return array<string, string>
     */
    private static function requiredFields(): array
    {
        return [
            'first_name' => 'სახელი',
            'last_name' => 'გვარი',
            'personal_id' => 'პირადი ნომერი',
            'phone_e164' => 'ტელეფონი',
            'city_id' => 'ქალაქი',
            'driver_type' => 'მძღოლის ტიპი',
            'vehicle_type' => 'ტრანსპორტის ტიპი',
            'vehicle_brand' => 'ბრენდი',
            'vehicle_model' => 'მოდელი',
            'vehicle_year' => 'წელი',
            'vehicle_color' => 'ფერი',
            'vehicle_plate' => 'სახელმწიფო ნომერი',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function requiredConsents(): array
    {
        return [
            'information_confirmed' => 'ინფორმაციის სისწორის დადასტურება',
            'terms_accepted' => 'წესებზე თანხმობა',
            'privacy_accepted' => 'მონაცემთა დამუშავებაზე თანხმობა',
        ];
    }

    /**
     * @return list<string>
     */
    private static function requiredDocumentTypes(): array
    {
        return ['id_front', 'id_back', 'license_front', 'license_back', 'vehicle_registration', 'vehicle_photo', 'selfie'];
    }

    private static function statusColor(string $state): string
    {
        return match ($state) {
            'approved' => 'success',
            'rejected' => 'danger',
            'submitted', 'pending', 'manual_review', 'needs_changes' => 'warning',
            'needs_completion' => 'info',
            default => 'gray',
        };
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
        return array_key_exists((string) $vehicleType, self::vehicleTypeOptions())
            ? (string) $vehicleType
            : 'moped';
    }

    private static function documentSummary(?DriverApplication $application): HtmlString
    {
        if ($application === null) {
            return new HtmlString('<span class="text-sm text-gray-500">განაცხადის შენახვის შემდეგ დოკუმენტები აქ გამოჩნდება.</span>');
        }

        $application->loadMissing('documents');

        if ($application->documents->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">დოკუმენტები ჯერ ატვირთული არ არის.</span>');
        }

        $items = $application->documents
            ->map(function (DriverApplicationDocument $document): string {
                $url = self::documentUrl($document);
                $label = e((self::documentTypeOptions()[$document->doc_type] ?? $document->doc_type).' · '.$document->status);
                $path = e($document->file_path);

                if ($url === null) {
                    return "<li class=\"rounded-lg border border-gray-200 p-3 dark:border-gray-700\"><div class=\"font-medium\">{$label}</div><code class=\"text-xs text-gray-500\">{$path}</code></li>";
                }

                return sprintf(
                    '<li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700"><a class="font-medium text-primary-600 hover:underline" href="%s" target="_blank" rel="noopener noreferrer">%s</a><br><code class="text-xs text-gray-500">%s</code></li>',
                    e($url),
                    $label,
                    $path,
                );
            })
            ->implode('');

        return new HtmlString("<ul class=\"grid gap-3 sm:grid-cols-2\">{$items}</ul>");
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
