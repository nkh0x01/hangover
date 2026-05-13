<?php

declare(strict_types=1);

namespace App\Modules\Driver\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverDocument;

/**
 * Produces the public "verification badge" view used by:
 *
 *   - The customer app's driver card during an active ride.
 *   - The driver app's profile screen.
 *   - The admin driver-detail page.
 *
 * Single source of truth for "is this driver allowed to take rides".
 * Combines the row-level `verification_status` with the per-document
 * approval state so callers don't need to know the doc-list invariants.
 */
final class DriverVerificationPresenter
{
    /** @var list<string> Matches the `doc_type` enum on driver_documents. */
    public const REQUIRED_DOCUMENTS = [
        'id_front',
        'id_back',
        'license_front',
        'license_back',
        'insurance',
        'vehicle_registration',
        'selfie_with_id',
    ];

    /**
     * @return array{
     *   verified: bool,
     *   verified_at: ?string,
     *   status: string,
     *   notes: ?string,
     *   missing: list<string>,
     *   expiring_soon: list<array{doc_type: string, expires_on: string}>,
     *   vehicle_verified: bool,
     * }
     */
    public function describe(Driver $driver): array
    {
        $driver->loadMissing(['currentVehicle']);

        $approvedByType = DriverDocument::query()
            ->where('driver_id', $driver->id)
            ->where('status', 'approved')
            ->get(['doc_type', 'expires_on'])
            ->keyBy('doc_type');

        $missing = array_values(array_diff(self::REQUIRED_DOCUMENTS, $approvedByType->keys()->all()));

        $expiringSoon = [];
        $threshold = now()->addDays(30);
        foreach ($approvedByType as $type => $doc) {
            if ($doc->expires_on !== null && $doc->expires_on->lessThanOrEqualTo($threshold)) {
                $expiringSoon[] = [
                    'doc_type' => (string) $type,
                    'expires_on' => $doc->expires_on->toDateString(),
                ];
            }
        }

        return [
            'verified' => $driver->verification_status === 'verified' && $missing === [],
            'verified_at' => $driver->verified_at?->toIso8601String(),
            'status' => (string) $driver->verification_status,
            'notes' => $driver->verification_notes,
            'missing' => $missing,
            'expiring_soon' => $expiringSoon,
            'vehicle_verified' => $driver->currentVehicle?->verified_at !== null,
        ];
    }

    /**
     * Returns true iff the driver can safely receive ride offers.
     * Composed of: verification badge ok + user active + driver
     * status approved + no high-severity unresolved fraud flags.
     */
    public function canAcceptOffers(Driver $driver): bool
    {
        $driver->loadMissing(['user']);

        if ($driver->verification_status !== 'verified') {
            return false;
        }
        if ($driver->status !== 'approved') {
            return false;
        }
        $user = $driver->user()->first();
        if ($user?->status !== 'active') {
            return false;
        }
        $blockingFlag = \App\Modules\Support\Models\FraudFlag::query()
            ->where('user_id', $user->id)
            ->where('severity', 'block')
            ->whereNull('resolved_at')
            ->exists();

        return ! $blockingFlag;
    }
}
