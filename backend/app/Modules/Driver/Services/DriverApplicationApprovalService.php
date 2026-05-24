<?php

declare(strict_types=1);

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\DriverDocument;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use Illuminate\Support\Facades\DB;

final class DriverApplicationApprovalService
{
    public function approve(DriverApplication $application, ?int $reviewerUserId = null, bool $autoApproved = false): DriverApplication
    {
        return DB::transaction(function () use ($application, $reviewerUserId, $autoApproved): DriverApplication {
            $application->loadMissing('documents');

            $cityId = $application->city_id
                ?? City::query()->where('is_active', true)->orderBy('id')->value('id');

            $driver = Driver::query()->firstOrCreate(
                ['user_id' => $application->user_id],
                [
                    'city_id' => $cityId,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by_user_id' => $reviewerUserId,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ],
            );

            $driver->update([
                'city_id' => $application->city_id ?? $driver->city_id,
                'status' => 'approved',
                'approved_at' => $driver->approved_at ?? now(),
                'approved_by_user_id' => $reviewerUserId,
                'verification_status' => 'verified',
                'verified_at' => $driver->verified_at ?? now(),
                'verification_notes' => null,
            ]);

            $vehicle = $application->vehicle_id !== null
                ? Vehicle::query()->where('driver_id', $driver->id)->find($application->vehicle_id)
                : null;

            $vehiclePayload = [
                'driver_id' => $driver->id,
                'type' => $this->supportedVehicleType($application->vehicle_type),
                'brand' => (string) $application->vehicle_brand,
                'model' => (string) $application->vehicle_model,
                'plate' => (string) $application->vehicle_plate,
                'color' => $application->vehicle_color,
                'year' => $application->vehicle_year,
                'is_active' => true,
                'verified_at' => now(),
                'verified_by_user_id' => $reviewerUserId,
            ];

            if ($vehicle instanceof Vehicle) {
                $vehicle->update($vehiclePayload);
            } else {
                $vehicle = Vehicle::query()->create($vehiclePayload);
            }

            $driver->update(['current_vehicle_id' => $vehicle->id]);

            foreach ($application->documents as $document) {
                $mappedType = $this->driverDocumentType($document->doc_type);
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
                        'reviewed_by_user_id' => $reviewerUserId,
                    ],
                );
            }

            $application->update([
                'status' => 'approved',
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewerUserId,
                'rejection_reason' => null,
                'admin_note' => $autoApproved ? 'auto_approved' : null,
            ]);

            return $application->refresh();
        });
    }

    private function driverDocumentType(string $applicationType): ?string
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

    private function supportedVehicleType(?string $vehicleType): string
    {
        return in_array($vehicleType, ['scooter_electric', 'scooter_petrol', 'moped', 'bicycle_electric', 'car'], true)
            ? (string) $vehicleType
            : 'moped';
    }
}
