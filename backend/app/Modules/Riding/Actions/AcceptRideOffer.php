<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Exceptions\RideNotOfferableException;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * CRITICAL CONCURRENCY-SAFE ACTION.
 *
 * If two drivers attempt to accept the same ride, or one driver tries
 * to accept two rides simultaneously, only one acceptance must succeed.
 * We enforce this with three layers:
 *
 *   1. SELECT ... FOR UPDATE on the ride row — serialises competing
 *      accepts within the same ride.
 *   2. Compound check that the offer for this (ride, driver) is still
 *      pending — guards against re-acceptance after a reject/timeout.
 *   3. The active_driver_lock partial unique index on rides — if the
 *      driver already has another in-flight ride, the UPDATE triggers a
 *      1062 duplicate-key error that we convert to a domain exception.
 */
final readonly class AcceptRideOffer
{
    public function __construct(private RideStateMachine $stateMachine) {}

    public function execute(Driver $driver, string $rideUlid): Ride
    {
        return DB::transaction(function () use ($driver, $rideUlid): Ride {
            /** @var Ride|null $ride */
            $ride = Ride::query()
                ->where('ulid', $rideUlid)
                ->lockForUpdate()
                ->first();

            if (! $ride) {
                throw new RideNotOfferableException('Ride not found.');
            }

            if ($ride->status !== RideStatus::Offered) {
                throw new RideNotOfferableException("Ride is not offerable (status={$ride->status->value}).");
            }

            $offer = RideOffer::query()
                ->where('ride_id', $ride->id)
                ->where('driver_id', $driver->id)
                ->where('response', 'pending')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $offer) {
                throw new RideNotOfferableException('Offer is no longer valid for this driver.');
            }

            $offer->update([
                'response' => 'accepted',
                'responded_at' => now(),
            ]);

            $ride->driver_id = $driver->id;
            $ride->vehicle_id = $driver->current_vehicle_id;

            try {
                $ride->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'rides_active_driver_uq')) {
                    throw new RideNotOfferableException('Driver already has an active ride.');
                }
                throw $e;
            }

            return $this->stateMachine->transition(
                ride: $ride,
                to: RideStatus::Accepted,
                actorType: 'driver',
                actorId: $driver->user_id,
            );
        });
    }
}
