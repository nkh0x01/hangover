<?php

declare(strict_types=1);

namespace App\Modules\Riding\Services;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Events\RideOffered;
use App\Modules\Riding\Jobs\ExpireRideOffer;
use App\Modules\Riding\Jobs\OfferRideToNextDriver;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideOffer;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Geo\Point;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The dispatch loop, factored into one pass: pick the next candidate
 * for one ride and either offer it, retry with a wider radius, or
 * declare no_drivers.
 *
 * The loop is *event-driven*, not a long-running worker: each pass is
 * a queued job, and the cycle re-enters via OfferRideToNextDriver or
 * the offer-expiry job. This is intentional — easier to reason about,
 * easier to test, and Redis owns the per-ride mutable state so we can
 * survive worker restarts.
 */
final readonly class DispatchService
{
    public function __construct(
        private DriverCandidateResolver $candidates,
        private DriverOfferQueue $offerQueue,
        private RideStateMachine $stateMachine,
    ) {}

    public function dispatchTick(Ride $ride): void
    {
        $ride->refresh();

        if (! in_array($ride->status, [RideStatus::Requested, RideStatus::Searching, RideStatus::Offered], true)) {
            $this->offerQueue->clear($ride->id);
            Log::channel('dispatch')->info('Ride no longer dispatchable', [
                'ride_id' => $ride->id,
                'status' => $ride->status->value,
            ]);

            return;
        }

        // First tick of a brand-new ride: enter searching.
        if ($ride->status === RideStatus::Requested) {
            $this->stateMachine->transition($ride, RideStatus::Searching, 'system');
            $ride->refresh();
        }

        $radiusKm = $this->resolveRadiusKm($ride);
        $pickup = $this->pickupFromGeometry($ride);

        $candidates = $this->candidates->resolve($ride, $pickup, $radiusKm);
        if ($candidates === []) {
            $this->onNoCandidates($ride);

            return;
        }

        $best = $candidates[0];
        $this->offerToDriver($ride, $best['driver'], (int) round($best['distance_m']));
    }

    private function offerToDriver(Ride $ride, Driver $driver, int $distanceM): void
    {
        $expiry = (int) config('realtime.offer.expiry_seconds', 12);
        $expiresAt = CarbonImmutable::now()->addSeconds($expiry);

        DB::transaction(function () use ($ride, $driver, $distanceM, $expiresAt): void {
            RideOffer::query()->updateOrCreate(
                ['ride_id' => $ride->id, 'driver_id' => $driver->id],
                [
                    'offered_at' => now(),
                    'expires_at' => $expiresAt,
                    'response' => 'pending',
                    'responded_at' => null,
                    'distance_to_pickup_m' => $distanceM,
                    'eta_seconds' => $this->etaSeconds($distanceM),
                ],
            );

            $this->stateMachine->transition($ride, RideStatus::Offered, 'system', reason: 'offer_dispatched');
        });

        $this->offerQueue->setCurrentOffer($ride->id, $driver->id, $expiry);

        RideOffered::dispatch(...$this->buildOfferedEventArgs($ride, $driver, $distanceM, $expiresAt));

        ExpireRideOffer::dispatch($ride->id, $driver->id)
            ->onQueue('realtime')
            ->delay($expiresAt);

        Log::channel('dispatch')->info('Offered', [
            'ride_id' => $ride->id,
            'driver_id' => $driver->id,
            'distance_m' => $distanceM,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function buildOfferedEventArgs(Ride $ride, Driver $driver, int $distanceM, CarbonImmutable $expiresAt): array
    {
        $event = RideOffered::build($ride, $driver, $distanceM, $expiresAt);

        return [$event->rideUlid, $event->driverUlid, $event->payload];
    }

    private function onNoCandidates(Ride $ride): void
    {
        $elapsedSeconds = $ride->requested_at ? now()->diffInSeconds($ride->requested_at, absolute: true) : 0;
        $timeout = (int) config('realtime.offer.search_timeout_seconds', 60);

        if ($elapsedSeconds >= $timeout) {
            $this->stateMachine->transition($ride, RideStatus::NoDrivers, 'system', reason: 'search_timeout');
            $this->offerQueue->clear($ride->id);
            Log::channel('dispatch')->info('No drivers — terminal', ['ride_id' => $ride->id]);

            return;
        }

        // Re-queue: try again in ~5 seconds, with a wider radius next time.
        OfferRideToNextDriver::dispatch($ride->id)
            ->onQueue('realtime')
            ->delay(now()->addSeconds(5));

        Log::channel('dispatch')->info('No candidates — retry scheduled', ['ride_id' => $ride->id, 'elapsed_s' => $elapsedSeconds]);
    }

    private function resolveRadiusKm(Ride $ride): float
    {
        $elapsed = $ride->requested_at ? now()->diffInSeconds($ride->requested_at, absolute: true) : 0;
        $initial = (float) config('realtime.offer.initial_radius_km', 3);
        $max = (float) config('realtime.offer.max_radius_km', 8);

        if ($elapsed >= 40) {
            return $max;
        }
        if ($elapsed >= 20) {
            return min($max, 5.0);
        }

        return $initial;
    }

    private function pickupFromGeometry(Ride $ride): Point
    {
        if (DB::getDriverName() !== 'mysql') {
            // SQLite (test environment): the spatial column doesn't
            // exist, so fall back to a deterministic Tbilisi point so
            // dispatch logic can still be exercised. Production always
            // uses MySQL.
            return new Point(41.7151, 44.8271);
        }
        $row = DB::selectOne(
            'SELECT ST_X(pickup_location) AS lng, ST_Y(pickup_location) AS lat FROM rides WHERE id = ?',
            [$ride->id],
        );

        return new Point((float) $row->lat, (float) $row->lng);
    }

    private function etaSeconds(int $distanceM): int
    {
        // 25 km/h baseline scooter speed; floors to ~5 seconds.
        $seconds = (int) round($distanceM / 1000 / 25 * 3600);

        return max(5, $seconds);
    }
}
