<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;

it('renders the admin settings page for an authenticated admin', function (): void {
    $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

    $this->actingAs($admin)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee('პილოტის პარამეტრები');
});

it('renders the pilot dashboard for an authenticated admin', function (): void {
    $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

    $this->actingAs($admin)
        ->get('/admin/pilot-dashboard-page')
        ->assertOk()
        ->assertSee('პილოტის ოპერაციული პანელი');
});

it('renders the diagnostics page for an authenticated admin', function (): void {
    $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

    $this->actingAs($admin)
        ->get('/admin/diagnostics')
        ->assertOk()
        ->assertSee('სისტემის დიაგნოსტიკა');
});
