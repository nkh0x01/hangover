<?php

declare(strict_types=1);

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;

final readonly class DriverProfileSummary
{
    public function __construct(private DriverVerificationPresenter $verification) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $driver = Driver::query()
            ->with(['currentVehicle', 'vehicles', 'user'])
            ->where('user_id', $user->id)
            ->first();

        $application = DriverApplication::query()
            ->with('documents')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($driver === null) {
            return $this->withoutDriver($application);
        }

        $vehicle = $driver->currentVehicle
            ?? $driver->vehicles->firstWhere('is_active', true);
        $vehicleStatus = $vehicle === null ? 'missing' : 'active';
        $verification = $this->verification->describe($driver);
        $canAcceptOffers = $this->verification->canAcceptOffers($driver);
        $canGoOnline = $canAcceptOffers && $vehicle !== null;

        return [
            'has_driver_profile' => true,
            'driver_profile_id' => $driver->id,
            'driver_profile_status' => $driver->status,
            'application_status' => $application?->status,
            'application_id' => $application?->id,
            'vehicle_status' => $vehicleStatus,
            'vehicle_id' => $vehicle?->id,
            'verification' => $verification,
            'can_go_online' => $canGoOnline,
            'reason_if_cannot_go_online' => $this->reasonForDriver($user, $driver, $vehicle, $canAcceptOffers),
            'today_earnings' => $canGoOnline ? $this->todayEarnings($driver) : null,
            'online_status' => $canGoOnline ? (bool) $driver->online : null,
            'rejection_reason' => ($application === null ? null : $application->rejection_reason) ?? $driver->approval_notes,
            'missing_required_fields' => [],
            'missing_documents' => $verification['missing'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutDriver(?DriverApplication $application): array
    {
        $missing = [];
        if ($application !== null) {
            $missing = $this->missingApplicationFields($application);
        }

        return [
            'has_driver_profile' => false,
            'driver_profile_id' => null,
            'driver_profile_status' => null,
            'application_status' => $application?->status,
            'application_id' => $application?->id,
            'vehicle_status' => null,
            'vehicle_id' => null,
            'verification' => null,
            'can_go_online' => false,
            'reason_if_cannot_go_online' => $this->reasonForApplication($application),
            'today_earnings' => null,
            'online_status' => null,
            'rejection_reason' => $application?->rejection_reason,
            'missing_required_fields' => $missing,
            'missing_documents' => $application === null
                ? []
                : $this->missingApplicationDocuments($application),
        ];
    }

    private function reasonForDriver(User $user, Driver $driver, ?Vehicle $vehicle, bool $canAcceptOffers): ?string
    {
        if ($user->isBlocked() || $driver->status === 'suspended') {
            return 'driver.suspended';
        }
        if ($driver->status === 'rejected') {
            return 'driver.rejected';
        }
        if (! $canAcceptOffers) {
            return 'driver.incomplete_profile';
        }
        if ($vehicle === null) {
            return 'driver.missing_vehicle';
        }

        return null;
    }

    private function reasonForApplication(?DriverApplication $application): string
    {
        if ($application === null) {
            return 'driver.no_profile';
        }

        return match ($application->status) {
            'draft' => 'application.draft',
            'submitted', 'pending' => 'application.pending_review',
            'rejected' => 'application.rejected',
            'needs_changes' => 'application.needs_changes',
            default => 'driver.no_profile',
        };
    }

    private function todayEarnings(Driver $driver): string
    {
        $sum = Ride::query()
            ->where('driver_id', $driver->id)
            ->where('status', RideStatus::Completed->value)
            ->whereDate('completed_at', today())
            ->sum('driver_earnings');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * @return list<string>
     */
    private function missingApplicationFields(DriverApplication $application): array
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

        return array_values(array_filter(
            $required,
            fn (string $field): bool => blank($application->{$field}),
        ));
    }

    /**
     * @return list<string>
     */
    private function missingApplicationDocuments(DriverApplication $application): array
    {
        $uploaded = $application->documents()
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('doc_type')
            ->all();

        return array_values(array_diff([
            'id_front',
            'id_back',
            'license_front',
            'license_back',
            'vehicle_registration',
            'vehicle_photo',
            'selfie',
        ], $uploaded));
    }
}
