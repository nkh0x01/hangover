<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;
use Illuminate\Support\Facades\DB;

/**
 * Closes out a ride: finalises distance / duration / amount, transitions
 * to `completed`. Payment capture and driver-payout writes live in the
 * Payment + Wallet modules; for Phase 1.5 we mirror the quoted amount
 * into final_amount so the UI has something to show.
 */
final readonly class CompleteTrip
{
    public function __construct(private RideStateMachine $stateMachine) {}

    public function execute(Driver $driver, Ride $ride, ?float $finalAmount = null, ?int $waitingSeconds = null): Ride
    {
        abort_unless($ride->driver_id === $driver->id, 403, 'auth.forbidden');

        DB::transaction(function () use ($ride, $finalAmount, $waitingSeconds): void {
            $ride->final_amount = $finalAmount ?? (float) $ride->quoted_amount;
            $ride->waiting_seconds = $waitingSeconds;

            if ($ride->started_at) {
                $ride->duration_seconds = max(0, now()->diffInSeconds($ride->started_at, absolute: true));
            }

            $ride->save();
        });

        return $this->stateMachine->transition(
            ride: $ride,
            to: RideStatus::Completed,
            actorType: 'driver',
            actorId: $driver->user_id,
        );
    }
}
