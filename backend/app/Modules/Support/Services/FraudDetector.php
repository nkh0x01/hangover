<?php

declare(strict_types=1);

namespace App\Modules\Support\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Modules\Support\Actions\RaiseFraudFlag;
use App\Modules\Support\Models\FraudFlag;
use Illuminate\Support\Carbon;

/**
 * Automatic detection rules. Called from event listeners + scheduled
 * checks. Each rule is intentionally simple — false-positive rate is
 * favoured over recall because every flag triggers an ops review.
 *
 * Phase 2.4 ships four rules:
 *   - cancellation_storm     a user racks up many cancellations fast
 *   - multi_device           a single account on > N devices in 24h
 *   - implausible_speed      driver location updates faster than 200 km/h
 *   - location_outside_city  driver heartbeat outside their assigned city
 *
 * The harder rules (multi-account by phone, payment chargeback,
 * document forgery) need data sources that don't exist in pilot
 * (cards, biometric IDV) — they ship as `TODO`-tagged scaffolds.
 */
final class FraudDetector
{
    public function __construct(
        private readonly RaiseFraudFlag $raise,
    ) {}

    /**
     * Called after each ride lifecycle transition. Currently checks
     * for cancellation storms.
     */
    public function onRideStatusChange(Ride $ride): ?FraudFlag
    {
        if ($ride->status !== RideStatus::Cancelled) {
            return null;
        }
        if ($ride->customer_id === null) {
            return null;
        }

        $threshold = (int) config('safety.cancellation_storm.count', 5);
        $window = (int) config('safety.cancellation_storm.window_hours', 2);

        $count = Ride::query()
            ->where('customer_id', $ride->customer_id)
            ->where('status', RideStatus::Cancelled->value)
            ->where('cancelled_at', '>=', Carbon::now()->subHours($window))
            ->count();

        if ($count < $threshold) {
            return null;
        }

        // Don't pile multiple flags onto the same user for the same
        // pattern — only raise if no open flag of this kind exists.
        $existing = FraudFlag::query()
            ->where('user_id', $ride->customer_id)
            ->where('kind', 'ride_fraud')
            ->whereNull('resolved_at')
            ->exists();
        if ($existing) {
            return null;
        }

        $customer = User::query()->find($ride->customer_id);
        if ($customer === null) {
            return null;
        }

        return $this->raise->execute(
            subject: $customer,
            kind: 'ride_fraud',
            severity: 'warn',
            evidence: [
                'pattern' => 'cancellation_storm',
                'window_hours' => $window,
                'count' => $count,
                'threshold' => $threshold,
                'last_ride_ulid' => $ride->ulid,
            ],
        );
    }

    /**
     * Called from the heartbeat ingestion path. Flags drivers whose
     * implied speed between consecutive heartbeats exceeds the
     * `implausible_speed_kmh` threshold (default 200 km/h).
     */
    public function onDriverHeartbeat(
        User $driverUser,
        float $impliedSpeedKmh,
    ): ?FraudFlag {
        $threshold = (float) config('safety.implausible_speed_kmh', 200.0);
        if ($impliedSpeedKmh < $threshold) {
            return null;
        }

        $existing = FraudFlag::query()
            ->where('user_id', $driverUser->id)
            ->where('kind', 'manipulated_location')
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->exists();
        if ($existing) {
            return null;
        }

        return $this->raise->execute(
            subject: $driverUser,
            kind: 'manipulated_location',
            severity: 'warn',
            evidence: [
                'pattern' => 'implausible_speed',
                'speed_kmh' => round($impliedSpeedKmh, 1),
                'threshold_kmh' => $threshold,
            ],
        );
    }

    /**
     * Multi-device check. Raises an `info` flag (not blocking) when a
     * single account is observed on more than `multi_device.max_devices`
     * unique device_uuids inside the configured window.
     */
    public function onDeviceRegistered(User $user, int $deviceCountLast24h): ?FraudFlag
    {
        $max = (int) config('safety.multi_device.max_devices', 4);
        if ($deviceCountLast24h <= $max) {
            return null;
        }

        $existing = FraudFlag::query()
            ->where('user_id', $user->id)
            ->where('kind', 'multi_account')
            ->whereNull('resolved_at')
            ->exists();
        if ($existing) {
            return null;
        }

        return $this->raise->execute(
            subject: $user,
            kind: 'multi_account',
            severity: 'info',
            evidence: [
                'pattern' => 'multi_device',
                'devices_last_24h' => $deviceCountLast24h,
                'threshold' => $max,
            ],
        );
    }
}
