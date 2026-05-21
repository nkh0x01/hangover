<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Exceptions\RideNotOfferableException;
use App\Modules\Riding\Jobs\OfferRideToNextDriver;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\Services\DriverOfferQueue;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;
use Illuminate\Support\Facades\DB;

final readonly class RejectRideOffer
{
    public function __construct(
        private RideStateMachine $stateMachine,
        private DriverOfferQueue $offerQueue,
    ) {}

    public function execute(Driver $driver, string $rideUlid): void
    {
        $rideId = DB::transaction(function () use ($driver, $rideUlid): int {
            $ride = Ride::query()
                ->where('ulid', $rideUlid)
                ->lockForUpdate()
                ->firstOrFail();

            $offer = RideOffer::query()
                ->where('ride_id', $ride->id)
                ->where('driver_id', $driver->id)
                ->where('response', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $offer) {
                throw new RideNotOfferableException('Offer is no longer pending for this driver.');
            }

            $offer->update([
                'response' => 'rejected',
                'responded_at' => now(),
            ]);

            // Step the ride back into searching; the dispatch job will
            // pick the next candidate (skipping this driver via the
            // rejects set).
            if ($ride->status === RideStatus::Offered) {
                $this->stateMachine->transition(
                    ride: $ride,
                    to: RideStatus::Searching,
                    actorType: 'driver',
                    actorId: $driver->user_id,
                    reason: 'offer_rejected',
                );
            }

            return $ride->id;
        });

        $this->offerQueue->markRejected($rideId, $driver->id);
        OfferRideToNextDriver::dispatch($rideId)->onQueue('realtime');
    }
}
