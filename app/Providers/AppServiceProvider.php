<?php

namespace App\Providers;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Users with the super_admin role automatically pass every permission check.
        Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole(RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
    }
}
