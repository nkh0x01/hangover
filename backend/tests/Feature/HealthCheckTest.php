<?php

declare(strict_types=1);

it('returns ok from /api/v1/health', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok');
});

it('exposes the app version endpoint', function (): void {
    $this->getJson('/api/v1/version')
        ->assertOk()
        ->assertJsonStructure(['data' => ['min_app_version', 'release']]);
});

it('exposes the public config endpoint', function (): void {
    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertJsonStructure(['data' => ['supported_locales', 'map_provider', 'payment_methods', 'reverb']]);
});
