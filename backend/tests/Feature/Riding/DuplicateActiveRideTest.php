<?php

declare(strict_types=1);

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Models\FareEstimate;
use App\Modules\Riding\Actions\CreateRideRequest as CreateRideRequestAction;
use App\Modules\Riding\Dto\RideRequestData;
use App\Modules\Riding\Exceptions\DuplicateActiveRideException;
use App\Modules\Riding\Jobs\DispatchRide;
use App\Support\Geo\Point;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('refuses a second concurrent ride for the same customer', function (): void {
    $city = City::factory()->create();
    $customer = User::factory()->create();

    $makeEstimate = fn () => FareEstimate::create([
        'customer_id' => $customer->id, 'city_id' => $city->id,
        'pickup_lat' => 41.7151, 'pickup_lng' => 44.8271,
        'dropoff_lat' => 41.7321, 'dropoff_lng' => 44.8271,
        'distance_km' => 2.0, 'duration_min' => 6,
        'base_fare' => 2.5, 'surge_multiplier' => 1.00,
        'total_amount' => 7.5, 'currency' => 'GEL',
        'expires_at' => now()->addMinutes(30),
    ]);

    $a = $makeEstimate();
    $b = $makeEstimate();

    $action = app(CreateRideRequestAction::class);

    $dto = fn (string $ulid) => new RideRequestData(
        fareEstimateUlid: $ulid,
        pickup: new Point(41.7151, 44.8271),
        pickupAddress: 'Pickup',
        dropoff: new Point(41.7321, 44.8271),
        dropoffAddress: 'Dropoff',
        paymentMethod: 'cash',
    );

    $action->execute($customer, $dto($a->ulid));

    expect(fn () => $action->execute($customer, $dto($b->ulid)))
        ->toThrow(DuplicateActiveRideException::class);

    Queue::assertPushed(DispatchRide::class);
});
