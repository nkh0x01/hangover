<?php

declare(strict_types=1);

namespace App\Modules\Driver\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverDocument;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin-facing action: mark a vehicle as verified after the physical
 * inspection (per `docs/phase-2.2/driver-approval-checklist.md`). The
 * action also re-evaluates the parent driver's verification status in
 * case this was the last gate before the badge.
 */
final class VerifyVehicle
{
    public function verify(Vehicle $vehicle, User $reviewer, ?string $notes = null): Vehicle
    {
        return $this->markVerification($vehicle, $reviewer, $notes, verified: true);
    }

    public function unverify(Vehicle $vehicle, User $reviewer, string $notes): Vehicle
    {
        return $this->markVerification($vehicle, $reviewer, $notes, verified: false);
    }

    private function markVerification(Vehicle $vehicle, User $reviewer, ?string $notes, bool $verified): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $reviewer, $notes, $verified): Vehicle {
            $vehicle->update([
                'verified_at' => $verified ? now() : null,
                'verified_by_user_id' => $reviewer->id,
                'verification_notes' => $notes,
            ]);

            $driver = $vehicle->driver;
            if ($driver !== null) {
                $this->reEvaluateDriver($driver);
            }

            activity('safety')
                ->causedBy($reviewer)
                ->performedOn($vehicle)
                ->withProperties([
                    'event' => $verified ? 'vehicle.verified' : 'vehicle.unverified',
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $vehicle->driver_id,
                    'notes' => $notes,
                ])
                ->log($verified ? 'vehicle.verified' : 'vehicle.unverified');

            Log::channel('security')->info($verified ? 'vehicle.verified' : 'vehicle.unverified', [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $vehicle->driver_id,
                'reviewer_id' => $reviewer->id,
            ]);

            return $vehicle;
        });
    }

    private function reEvaluateDriver(Driver $driver): void
    {
        $requiredTypes = ['id_front', 'id_back', 'license_front', 'license_back', 'insurance', 'vehicle_registration', 'selfie_with_id'];

        $approvedTypes = DriverDocument::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'approved')
            ->pluck('doc_type')
            ->all();

        $allDocsOk = array_diff($requiredTypes, $approvedTypes) === [];
        $vehicle = $driver->currentVehicle;
        $vehicleOk = $vehicle !== null && $vehicle->verified_at !== null;

        if ($allDocsOk && $vehicleOk) {
            $driver->update([
                'verification_status' => 'verified',
                'verified_at' => $driver->verified_at ?? now(),
            ]);
        } elseif ($driver->verification_status === 'verified') {
            // A previously-verified driver got their vehicle un-verified.
            $driver->update(['verification_status' => 'in_review']);
        }
    }
}
