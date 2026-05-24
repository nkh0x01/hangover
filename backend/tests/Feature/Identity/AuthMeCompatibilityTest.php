<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the authenticated customer from the compatibility auth me endpoint', function (): void {
    $user = User::factory()->create([
        'phone_e164' => '+995555220001',
        'type' => 'customer',
    ]);
    Sanctum::actingAs($user, ['customer']);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.type', 'customer')
        ->assertJsonPath('data.phone', '+995555220001');
});

it('returns driver context from the compatibility auth me endpoint', function (): void {
    $user = User::factory()->driver()->create([
        'phone_e164' => '+995555220002',
    ]);
    Sanctum::actingAs($user, ['driver:onboarding']);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.type', 'driver')
        ->assertJsonPath('data.phone', '+995555220002')
        ->assertJsonPath('data.driver_context.has_driver_profile', false)
        ->assertJsonPath('data.driver_context.needs_application', true);
});
