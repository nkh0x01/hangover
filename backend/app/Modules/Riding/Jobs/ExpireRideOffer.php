<?php

declare(strict_types=1);

namespace App\Modules\Riding\Jobs;

use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\Services\DispatchService;
use App\Modules\Riding\Services\DriverOfferQueue;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Fires at offer.expires_at. If the offer is still pending, mark it as
 * timeout, blacklist the driver in the ride's reject set, and bounce
 * back into the dispatch loop.
 *
 * Safe to no-op if the driver already accepted, rejected, or the ride
 * advanced for another reason.
 */
final class ExpireRideOffer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(
        public readonly int $rideId,
        public readonly int $driverId,
    ) {}

    public function handle(
        DispatchService $dispatch,
        DriverOfferQueue $offerQueue,
        RideStateMachine $stateMachine,
    ): void {
        $rideId = $this->rideId;
        $driverId = $this->driverId;

        $needsRetry = DB::transaction(function () use ($rideId, $driverId, $stateMachine): bool {
            $ride = Ride::query()->whereKey($rideId)->lockForUpdate()->first();
            if (! $ride) {
                return false;
            }

            $offer = RideOffer::query()
                ->where('ride_id', $rideId)
                ->where('driver_id', $driverId)
                ->where('response', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $offer) {
                return false;
            }

            $offer->update([
                'response' => 'timeout',
                'responded_at' => now(),
            ]);

            if ($ride->status === RideStatus::Offered) {
                $stateMachine->transition(
                    ride: $ride,
                    to: RideStatus::Searching,
                    actorType: 'system',
                    reason: 'offer_timeout',
                );
            }

            return $ride->status->isActive();
        });

        if ($needsRetry) {
            $offerQueue->markRejected($rideId, $driverId);
            $offerQueue->clearCurrentOffer($rideId);

            OfferRideToNextDriver::dispatch($rideId)->onQueue('realtime');
        }
    }
}
