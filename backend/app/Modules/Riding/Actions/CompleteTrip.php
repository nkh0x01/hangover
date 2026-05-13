<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Payment\Actions\SettleRidePayment;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Closes out a ride: finalises distance / duration / amount, transitions
 * to `completed`, and triggers payment settlement.
 *
 * Payment settlement is best-effort: a gateway failure does NOT block
 * the ride from completing — the driver still drove. The payment row
 * lands in `failed` state and ops resolves it via the finance panel.
 * We never throw past the SettleRidePayment call.
 */
final readonly class CompleteTrip
{
    public function __construct(
        private RideStateMachine $stateMachine,
        private SettleRidePayment $settlePayment,
    ) {}

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

        $completed = $this->stateMachine->transition(
            ride: $ride,
            to: RideStatus::Completed,
            actorType: 'driver',
            actorId: $driver->user_id,
        );

        try {
            $this->settlePayment->execute($completed);
        } catch (Throwable $e) {
            // Catch and log — the ride is already completed. Ops
            // will retry via the finance panel.
            Log::channel('payment')->error('SettleRidePayment threw during CompleteTrip', [
                'ride_id' => $ride->id,
                'ride_ulid' => $ride->ulid,
                'error' => $e->getMessage(),
            ]);
        }

        return $completed->refresh();
    }
}
