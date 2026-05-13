<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverDocument;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Admin-facing action: approve or reject a single driver document.
 *
 * On approval: if every required document is approved AND the driver's
 * active vehicle is verified, the driver flips to
 * `verification_status = verified` + `verified_at = now`. Until then
 * the driver stays in `in_review`.
 *
 * On rejection: the driver flips to `verification_status = rejected`
 * (regardless of any other docs' state) so the safety dashboard
 * surfaces it immediately for ops to message the driver.
 *
 * Both branches write to `spatie/activitylog` under
 * `log_name = 'safety'`.
 */
final class ReviewDriverDocument
{
    /** @var list<string> */
    private const REQUIRED_TYPES = [
        'id_front',
        'id_back',
        'license_front',
        'license_back',
        'insurance',
        'vehicle_registration',
        'selfie_with_id',
    ];

    public function approve(DriverDocument $doc, User $reviewer, ?string $notes = null): DriverDocument
    {
        return $this->review($doc, $reviewer, 'approved', $notes);
    }

    public function reject(DriverDocument $doc, User $reviewer, string $notes): DriverDocument
    {
        if (trim($notes) === '') {
            throw new InvalidArgumentException('Rejection requires a notes message.');
        }

        return $this->review($doc, $reviewer, 'rejected', $notes);
    }

    private function review(DriverDocument $doc, User $reviewer, string $status, ?string $notes): DriverDocument
    {
        return DB::transaction(function () use ($doc, $reviewer, $status, $notes): DriverDocument {
            $doc->update([
                'status' => $status,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $driver = $doc->driver;
            if ($driver === null) {
                return $doc;
            }

            if ($status === 'rejected') {
                $driver->update([
                    'verification_status' => 'rejected',
                    'verification_notes' => $notes,
                ]);
            } else {
                $this->maybeMarkVerified($driver);
            }

            activity('safety')
                ->causedBy($reviewer)
                ->performedOn($doc)
                ->withProperties([
                    'event' => "driver.document.{$status}",
                    'doc_type' => $doc->doc_type,
                    'driver_id' => $driver->id,
                    'notes' => $notes,
                ])
                ->log("driver.document.{$status}");

            Log::channel('security')->info("driver.document.{$status}", [
                'driver_id' => $driver->id,
                'doc_type' => $doc->doc_type,
                'reviewer_id' => $reviewer->id,
            ]);

            return $doc;
        });
    }

    private function maybeMarkVerified(Driver $driver): void
    {
        $approvedTypes = DriverDocument::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'approved')
            ->pluck('doc_type')
            ->all();

        $missing = array_diff(self::REQUIRED_TYPES, $approvedTypes);
        if ($missing !== []) {
            $driver->update(['verification_status' => 'in_review']);

            return;
        }

        $vehicle = $driver->currentVehicle;
        if ($vehicle === null || $vehicle->verified_at === null) {
            $driver->update(['verification_status' => 'in_review']);

            return;
        }

        $driver->update([
            'verification_status' => 'verified',
            'verified_at' => $driver->verified_at ?? now(),
        ]);
    }
}
