<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Dto\RideRequestData;
use App\Modules\Riding\Exceptions\DuplicateActiveRideException;
use App\Modules\Riding\Exceptions\RideNotOfferableException;
use App\Modules\Riding\Jobs\DispatchRide;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Ulid;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Build and persist a ride in the `requested` state, then kick off the
 * dispatch loop as a queued job so the API response time isn't bound by
 * the candidate search.
 *
 * The active_customer_lock partial unique index in the rides table
 * guarantees that one customer cannot have two active rides; on a
 * collision we raise DuplicateActiveRideException so the client can
 * surface a clean error.
 */
final readonly class CreateRideRequest
{
    public function execute(User $customer, RideRequestData $data): Ride
    {
        $estimate = FareEstimate::query()
            ->where('ulid', $data->fareEstimateUlid)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $estimate) {
            throw new RideNotOfferableException('Fare estimate not found.');
        }
        if ($estimate->isExpired()) {
            throw new RideNotOfferableException('Fare estimate has expired.');
        }

        $testPhones = (array) config('pilot.test_phone_numbers', []);
        $isTestRide = in_array((string) $customer->phone_e164, $testPhones, true);
        $cohort = $isTestRide ? (string) config('pilot.cohort') : null;

        try {
            $ride = DB::transaction(
                fn (): Ride => DB::getDriverName() === 'mysql'
                    ? $this->createMysqlRide($customer, $data, $estimate, $isTestRide, $cohort)
                    : $this->createPortableRide($customer, $data, $estimate, $isTestRide, $cohort),
            );
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e, 'rides_active_customer_uq')) {
                throw new DuplicateActiveRideException('Customer already has an active ride.');
            }
            throw $e;
        }

        // Kick off dispatch asynchronously — the API returns immediately
        // with the ride in `requested` state. The job moves it to
        // `searching` then `offered` as soon as a driver is picked.
        DispatchRide::dispatch($ride->id)->onQueue('realtime');

        return $ride;
    }

    private function createMysqlRide(
        User $customer,
        RideRequestData $data,
        FareEstimate $estimate,
        bool $isTestRide,
        ?string $cohort,
    ): Ride {
        $ulid = Ulid::new();
        $now = now();

        DB::insert(
            'INSERT INTO rides (
                ulid, customer_id, city_id, status,
                pickup_address, dropoff_address, pickup_location, dropoff_location,
                fare_estimate_id, quoted_amount, surge_multiplier, currency,
                payment_method, is_test_ride, pilot_cohort,
                requested_at, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ST_GeomFromText(CONCAT(\'POINT(\', ?, \' \', ?, \')\'), 4326),
                      ST_GeomFromText(CONCAT(\'POINT(\', ?, \' \', ?, \')\'), 4326),
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )',
            [
                $ulid,
                $customer->id,
                $estimate->city_id,
                RideStatus::Requested->value,
                $data->pickupAddress,
                $data->dropoffAddress,
                $data->pickup->lng,
                $data->pickup->lat,
                $data->dropoff->lng,
                $data->dropoff->lat,
                $estimate->id,
                $estimate->total_amount,
                $estimate->surge_multiplier,
                $estimate->currency,
                $data->paymentMethod,
                $isTestRide ? 1 : 0,
                $cohort !== '' ? $cohort : null,
                $now,
                $now,
                $now,
            ],
        );

        return Ride::query()->where('ulid', $ulid)->firstOrFail();
    }

    private function createPortableRide(
        User $customer,
        RideRequestData $data,
        FareEstimate $estimate,
        bool $isTestRide,
        ?string $cohort,
    ): Ride {
        $ride = new Ride([
            'ulid' => Ulid::new(),
            'customer_id' => $customer->id,
            'city_id' => $estimate->city_id,
            'status' => RideStatus::Requested,
            'pickup_address' => $data->pickupAddress,
            'dropoff_address' => $data->dropoffAddress,
            'fare_estimate_id' => $estimate->id,
            'quoted_amount' => $estimate->total_amount,
            'surge_multiplier' => $estimate->surge_multiplier,
            'currency' => $estimate->currency,
            'payment_method' => $data->paymentMethod,
            'is_test_ride' => $isTestRide,
            'pilot_cohort' => $cohort !== '' ? $cohort : null,
            'requested_at' => now(),
        ]);
        $ride->save();

        return $ride->refresh();
    }

    private function isUniqueViolation(QueryException $e, string $indexName): bool
    {
        $msg = $e->getMessage();

        if (str_contains($msg, $indexName)) {
            return true;
        }

        // MySQL duplicate-key SQLSTATE without the index name in the
        // message (rare, but possible on some MySQL flavours): fall
        // back to checking the SQLSTATE error code.
        return isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062;
    }
}
