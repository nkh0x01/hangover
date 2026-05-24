<?php

declare(strict_types=1);

namespace App\Modules\Driver\Http\Controllers;

use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\DriverApplicationDocument;
use App\Modules\Driver\Services\DriverProfileSummary;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ApplicationController
{
    public function __construct(private DriverProfileSummary $summary) {}

    public function show(Request $request): JsonResponse
    {
        $application = $this->applicationFor($request, create: false);

        return new JsonResponse([
            'data' => $application ? $this->serialize($application) : null,
            'driver_context' => $this->summary->forUser($this->user($request)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $application = $this->upsert($request);

        return new JsonResponse([
            'data' => $this->serialize($application),
            'driver_context' => $this->summary->forUser($this->user($request)),
        ], 201);
    }

    public function update(Request $request): JsonResponse
    {
        $application = $this->upsert($request);

        return new JsonResponse([
            'data' => $this->serialize($application),
            'driver_context' => $this->summary->forUser($this->user($request)),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $application = $this->applicationFor($request, create: true);
        $missing = $this->missingFields($application);

        if ($missing !== []) {
            throw new HttpException(422, 'driver.application_incomplete');
        }

        $application->update([
            'status' => 'pending',
            'submitted_at' => now(),
            'rejection_reason' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ]);

        return new JsonResponse([
            'data' => $this->serialize($application->refresh()),
            'driver_context' => $this->summary->forUser($this->user($request)),
        ]);
    }

    public function document(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doc_type' => ['required', 'string', Rule::in($this->documentTypes())],
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,pdf,heic,webp'],
        ]);

        $application = $this->applicationFor($request, create: true);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw new HttpException(422, 'driver.document_hash_failed');
        }

        $sha = hash_file('sha256', $realPath);
        if ($sha === false) {
            throw new HttpException(422, 'driver.document_hash_failed');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = sprintf('applications/%d/%s/%s.%s', $application->id, $data['doc_type'], $sha, $ext);
        $disk = (string) config('drivers.docs_disk', config('filesystems.default', 'local'));

        $document = DB::transaction(function () use ($application, $data, $file, $sha, $path, $disk): DriverApplicationDocument {
            DriverApplicationDocument::query()
                ->where('application_id', $application->id)
                ->where('doc_type', $data['doc_type'])
                ->delete();

            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

            return DriverApplicationDocument::create([
                'application_id' => $application->id,
                'doc_type' => $data['doc_type'],
                'file_path' => $path,
                'file_sha256' => $sha,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'status' => 'pending',
            ]);
        });

        return new JsonResponse([
            'data' => [
                'id' => $document->id,
                'doc_type' => $document->doc_type,
                'status' => $document->status,
                'preview_name' => basename($document->file_path),
            ],
            'application' => $this->serialize($application->refresh()),
            'driver_context' => $this->summary->forUser($this->user($request)),
        ], 201);
    }

    private function upsert(Request $request): DriverApplication
    {
        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'personal_id' => ['nullable', 'digits_between:9,11'],
            'phone_e164' => ['nullable', 'regex:/^\+9955\d{8}$/'],
            'email' => ['nullable', 'email'],
            'birth_date' => ['nullable', 'date_format:Y-m-d'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'service_zone' => ['nullable', 'string', 'max:80'],
            'driver_type' => ['nullable', Rule::in(['moto', 'car', 'courier'])],
            'vehicle_type' => ['nullable', Rule::in(['scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric', 'car'])],
            'vehicle_brand' => ['nullable', 'string', 'max:60'],
            'vehicle_model' => ['nullable', 'string', 'max:60'],
            'vehicle_year' => ['nullable', 'integer', 'between:1980,2035'],
            'vehicle_color' => ['nullable', 'string', 'max:30'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'engine_cc' => ['nullable', 'string', 'max:20'],
            'insurance_expires_on' => ['nullable', 'date_format:Y-m-d'],
            'inspection_expires_on' => ['nullable', 'date_format:Y-m-d'],
            'information_confirmed' => ['nullable', 'boolean'],
            'terms_accepted' => ['nullable', 'boolean'],
            'privacy_accepted' => ['nullable', 'boolean'],
        ]);

        $application = $this->applicationFor($request, create: true);
        if (in_array($application->status, ['submitted', 'pending', 'approved'], true)) {
            throw new HttpException(409, 'driver.application_locked');
        }

        $application->fill($data);
        if (in_array($application->status, ['rejected', 'needs_changes'], true)) {
            $application->status = 'draft';
            $application->rejection_reason = null;
            $application->reviewed_at = null;
            $application->reviewed_by_user_id = null;
        }
        $application->save();

        return $application->refresh();
    }

    private function applicationFor(Request $request, bool $create): ?DriverApplication
    {
        $user = $this->user($request);
        $query = DriverApplication::query()
            ->with('documents')
            ->where('user_id', $user->id);

        $application = $query->first();
        if ($application !== null || ! $create) {
            return $application;
        }

        return DriverApplication::create([
            'user_id' => $user->id,
            'phone_e164' => $user->phone_e164,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'status' => 'draft',
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new HttpException(401, 'auth.invalid_token');
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function missingFields(DriverApplication $application): array
    {
        $required = [
            'first_name',
            'last_name',
            'personal_id',
            'phone_e164',
            'driver_type',
            'vehicle_type',
            'vehicle_brand',
            'vehicle_model',
            'vehicle_plate',
        ];

        $missing = array_values(array_filter(
            $required,
            fn (string $field): bool => blank($application->{$field}),
        ));

        foreach (['information_confirmed', 'terms_accepted', 'privacy_accepted'] as $field) {
            if (! (bool) $application->{$field}) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DriverApplication $application): array
    {
        $application->loadMissing('documents');

        return [
            'id' => $application->id,
            'status' => $application->status,
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'personal_id' => $application->personal_id,
            'phone_e164' => $application->phone_e164,
            'email' => $application->email,
            'birth_date' => $this->dateString($application->birth_date),
            'city_id' => $application->city_id,
            'service_zone' => $application->service_zone,
            'driver_type' => $application->driver_type,
            'vehicle_type' => $application->vehicle_type,
            'vehicle_brand' => $application->vehicle_brand,
            'vehicle_model' => $application->vehicle_model,
            'vehicle_year' => $application->vehicle_year,
            'vehicle_color' => $application->vehicle_color,
            'vehicle_plate' => $application->vehicle_plate,
            'engine_cc' => $application->engine_cc,
            'insurance_expires_on' => $this->dateString($application->insurance_expires_on),
            'inspection_expires_on' => $this->dateString($application->inspection_expires_on),
            'information_confirmed' => (bool) $application->information_confirmed,
            'terms_accepted' => (bool) $application->terms_accepted,
            'privacy_accepted' => (bool) $application->privacy_accepted,
            'rejection_reason' => $application->rejection_reason,
            'admin_note' => $application->admin_note,
            'submitted_at' => $this->dateTimeString($application->submitted_at),
            'reviewed_at' => $this->dateTimeString($application->reviewed_at),
            'documents' => $application->documents->map(fn (DriverApplicationDocument $document): array => [
                'id' => $document->id,
                'doc_type' => $document->doc_type,
                'status' => $document->status,
                'preview_name' => basename($document->file_path),
                'review_notes' => $document->review_notes,
            ])->values()->all(),
            'missing_required_fields' => $this->missingFields($application),
        ];
    }

    /**
     * @return list<string>
     */
    private function documentTypes(): array
    {
        return [
            'id_front',
            'id_back',
            'license_front',
            'license_back',
            'vehicle_registration',
            'vehicle_photo',
            'selfie',
            'insurance',
        ];
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->toDateString()
            : Carbon::parse((string) $value)->toDateString();
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? Carbon::instance($value)->toIso8601String()
            : Carbon::parse((string) $value)->toIso8601String();
    }
}
