<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Persists a freshly-uploaded document for a driver. Computes the
 * SHA-256 so the admin reviewer can detect duplicate / re-submitted
 * files, and flips the driver's `verification_status` from `pending`
 * → `in_review` so the safety dashboard surfaces it.
 *
 * The file lands in the `drivers` disk (S3 in prod, local in dev).
 * Storage path: `documents/{driver_id}/{doc_type}/{sha}.{ext}`.
 *
 * The action does NOT approve anything. Approval is a separate admin
 * action — see {@see ApproveDriverDocument}.
 */
final class SubmitDriverDocument
{
    public function execute(
        Driver $driver,
        string $docType,
        UploadedFile $file,
        ?string $expiresOn = null,
    ): DriverDocument {
        if (! in_array($docType, $this->allowedTypes(), true)) {
            throw new RuntimeException("Unknown doc_type: {$docType}");
        }

        $sha = hash_file('sha256', $file->getRealPath());
        if ($sha === false) {
            throw new RuntimeException('Could not hash uploaded file.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = sprintf('documents/%d/%s/%s.%s', $driver->id, $docType, $sha, $ext);

        $disk = (string) config('drivers.docs_disk', config('filesystems.default', 'local'));

        return DB::transaction(function () use ($driver, $docType, $file, $sha, $path, $disk, $expiresOn): DriverDocument {
            // Replace any existing pending row for the same type — drivers
            // re-submit when admin rejects.
            DriverDocument::query()
                ->where('driver_id', $driver->id)
                ->where('doc_type', $docType)
                ->whereIn('status', ['pending', 'rejected'])
                ->delete();

            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

            $doc = DriverDocument::create([
                'driver_id' => $driver->id,
                'doc_type' => $docType,
                'file_path' => $path,
                'file_sha256' => $sha,
                'expires_on' => $expiresOn,
                'status' => 'pending',
            ]);

            if ($driver->verification_status === 'pending') {
                $driver->update(['verification_status' => 'in_review']);
            }

            Log::channel('security')->info('driver.document.submitted', [
                'driver_id' => $driver->id,
                'doc_type' => $docType,
                'sha256' => $sha,
            ]);

            return $doc;
        });
    }

    /** @var list<string> Matches the `doc_type` enum on the driver_documents table. */
    public const DOC_TYPES = [
        'id_front',
        'id_back',
        'license_front',
        'license_back',
        'insurance',
        'vehicle_registration',
        'selfie_with_id',
    ];

    /** @return list<string> */
    private function allowedTypes(): array
    {
        return self::DOC_TYPES;
    }
}
