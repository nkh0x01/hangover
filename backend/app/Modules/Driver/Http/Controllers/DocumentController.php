<?php

declare(strict_types=1);

namespace App\Modules\Driver\Http\Controllers;

use App\Modules\Driver\Actions\SubmitDriverDocument;
use App\Modules\Driver\Services\DriverVerificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `POST /api/v1/driver/documents`
 *
 * Driver-side document submission. Accepts multipart/form-data:
 *   - `doc_type`   : id|license|registration|tech_inspection|insurance|headshot|background_check
 *   - `expires_on` : optional ISO date
 *   - `file`       : the document (image or PDF, ≤ 8 MB)
 *
 * Auth: a driver-typed user. The driver must already be onboarded
 * via the admin panel (a Driver row exists for them).
 */
final class DocumentController
{
    public function __construct(
        private readonly SubmitDriverDocument $action,
        private readonly DriverVerificationPresenter $presenter,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'doc_type' => ['required', 'string', 'in:id_front,id_back,license_front,license_back,insurance,vehicle_registration,selfie_with_id'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,pdf,heic,webp'],
        ]);

        $user = $request->user();
        $driver = $user !== null
            ? \App\Modules\Driver\Models\Driver::query()->where('user_id', $user->id)->first()
            : null;
        if ($driver === null) {
            throw new HttpException(404, 'driver.not_onboarded');
        }

        $doc = $this->action->execute(
            driver: $driver,
            docType: $data['doc_type'],
            file: $request->file('file'),
            expiresOn: $data['expires_on'] ?? null,
        );

        return new JsonResponse([
            'data' => [
                'id' => $doc->id,
                'doc_type' => $doc->doc_type,
                'status' => $doc->status,
                'expires_on' => $doc->expires_on?->toDateString(),
            ],
            'verification' => $this->presenter->describe($driver->refresh()),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $driver = $user !== null
            ? \App\Modules\Driver\Models\Driver::query()->where('user_id', $user->id)->first()
            : null;
        if ($driver === null) {
            throw new HttpException(404, 'driver.not_onboarded');
        }

        $docs = \App\Modules\Driver\Models\DriverDocument::query()
            ->where('driver_id', $driver->id)
            ->orderBy('doc_type')
            ->get(['id', 'doc_type', 'status', 'expires_on', 'review_notes', 'reviewed_at']);

        return new JsonResponse([
            'data' => $docs->map(fn (\App\Modules\Driver\Models\DriverDocument $d): array => [
                'id' => $d->id,
                'doc_type' => $d->doc_type,
                'status' => $d->status,
                'expires_on' => $d->expires_on?->toDateString(),
                'review_notes' => $d->review_notes,
                'reviewed_at' => $d->reviewed_at?->toIso8601String(),
            ])->all(),
            'verification' => $this->presenter->describe($driver),
        ]);
    }
}
