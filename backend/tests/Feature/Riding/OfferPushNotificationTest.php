<?php

declare(strict_types=1);

use App\Modules\Communication\Contracts\PushGateway;
use App\Modules\Communication\Contracts\PushResult;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;
use App\Modules\Riding\Events\RideOffered;
use App\Modules\Riding\Listeners\SendOfferPush;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Support\Ulid;

/**
 * In-memory PushGateway double — records every call.
 */
final class RecordingPushGateway implements PushGateway
{
    /** @var array<int, array{token:string,title:string,body:string,data:array<string,string>}> */
    public array $sent = [];

    public bool $tokenInvalid = false;

    public function send(string $token, string $title, string $body, array $data = []): PushResult
    {
        $this->sent[] = compact('token', 'title', 'body', 'data');
        if ($this->tokenInvalid) {
            return PushResult::failed('UNREGISTERED', 'token invalid', tokenInvalid: true);
        }

        return PushResult::ok('msg-'.count($this->sent));
    }

    public function multicast(array $tokens, string $title, string $body, array $data = []): array
    {
        return array_map(fn (string $t) => $this->send($t, $title, $body, $data), $tokens);
    }
}

function offerEventFor(User $driverUser, Driver $driver): RideOffered
{
    $city = $driver->city;
    $customer = User::factory()->create();

    $ride = new Ride([
        'ulid' => Ulid::new(),
        'customer_id' => $customer->id,
        'city_id' => $city->id,
        'status' => RideStatus::Offered,
        'pickup_address' => 'Pickup',
        'dropoff_address' => 'Dropoff',
        'quoted_amount' => 7.5,
        'surge_multiplier' => 1.0,
        'currency' => 'GEL',
        'payment_method' => 'cash',
        'requested_at' => now(),
    ]);
    $ride->save();

    return new RideOffered($ride->ulid, $driverUser->ulid, [
        'ride_ulid' => $ride->ulid,
        'expires_at' => now()->addSeconds(12)->format(DATE_ATOM),
        'pickup' => ['address' => 'Pickup'],
        'dropoff' => ['address' => 'Dropoff'],
        'distance_to_pickup_m' => 500,
        'fare' => ['amount' => 7.5, 'currency' => 'GEL'],
    ]);
}

it('sends an offer push when the driver has an active fcm token', function (): void {
    $gateway = new RecordingPushGateway;
    app()->instance(PushGateway::class, $gateway);

    $city = City::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id]);
    Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

    UserDevice::create([
        'user_id' => $driver->user_id,
        'device_uuid' => 'dev-1',
        'platform' => 'android',
        'app_version' => '0.1.0',
        'fcm_token' => 'fcm-token-AAA',
        'push_enabled' => true,
        'last_active_at' => now(),
    ]);

    /** @var SendOfferPush $listener */
    $listener = app(SendOfferPush::class);
    $listener->handle(offerEventFor($driver->user, $driver));

    expect($gateway->sent)->toHaveCount(1);
    expect($gateway->sent[0]['token'])->toBe('fcm-token-AAA');
    expect($gateway->sent[0]['data']['kind'])->toBe('ride.offered');
    expect($gateway->sent[0]['data']['ride_ulid'])->not->toBe('');
});

it('purges the token when FCM reports it as invalid', function (): void {
    $gateway = new RecordingPushGateway;
    $gateway->tokenInvalid = true;
    app()->instance(PushGateway::class, $gateway);

    $city = City::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id]);
    Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

    $device = UserDevice::create([
        'user_id' => $driver->user_id,
        'device_uuid' => 'dev-2',
        'platform' => 'android',
        'app_version' => '0.1.0',
        'fcm_token' => 'fcm-token-DEAD',
        'push_enabled' => true,
        'last_active_at' => now(),
    ]);

    app(SendOfferPush::class)->handle(offerEventFor($driver->user, $driver));

    $device->refresh();
    expect($device->fcm_token)->toBeNull();
    expect($device->push_enabled)->toBeFalse();
});

it('skips push when the driver has no fcm token', function (): void {
    $gateway = new RecordingPushGateway;
    app()->instance(PushGateway::class, $gateway);

    $city = City::factory()->create();
    $driver = Driver::factory()->create(['city_id' => $city->id]);
    Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

    UserDevice::create([
        'user_id' => $driver->user_id,
        'device_uuid' => 'dev-3',
        'platform' => 'android',
        'app_version' => '0.1.0',
        'fcm_token' => null,
        'push_enabled' => false,
        'last_active_at' => now(),
    ]);

    app(SendOfferPush::class)->handle(offerEventFor($driver->user, $driver));

    expect($gateway->sent)->toBeEmpty();
});
