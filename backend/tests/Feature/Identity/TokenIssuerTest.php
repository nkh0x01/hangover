<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserDevice;
use App\Modules\Identity\Services\TokenIssuer;

it('issues driver ability for approved drivers even when relation is not preloaded', function (): void {
    $user = User::factory()->driver()->create();
    Driver::factory()->create([
        'user_id' => $user->id,
        'status' => 'approved',
    ]);
    $device = UserDevice::create([
        'user_id' => $user->id,
        'device_uuid' => 'driver-ios-1',
        'platform' => 'ios',
        'app_version' => '0.1.0',
    ]);

    $token = app(TokenIssuer::class)->issue($user->fresh(), $device);

    expect($token['abilities'])->toBe(['driver']);
});

it('keeps onboarding ability for drivers that are not approved', function (): void {
    $user = User::factory()->driver()->create();
    Driver::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);
    $device = UserDevice::create([
        'user_id' => $user->id,
        'device_uuid' => 'driver-ios-2',
        'platform' => 'ios',
        'app_version' => '0.1.0',
    ]);

    $token = app(TokenIssuer::class)->issue($user->fresh(), $device);

    expect($token['abilities'])->toBe(['driver:onboarding']);
});

it('uses onboarding ability for driver OTP purpose before approval', function (): void {
    $user = User::factory()->driver()->create();
    $device = UserDevice::create([
        'user_id' => $user->id,
        'device_uuid' => 'driver-ios-3',
        'platform' => 'ios',
        'app_version' => '0.1.0',
    ]);

    $token = app(TokenIssuer::class)->issue($user, $device, purpose: 'driver_signup');

    expect($token['abilities'])->toBe(['driver:onboarding']);
});
