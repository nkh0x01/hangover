<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::query()->orderBy('id')->first();

        // On staging/production we read the demo password from env so the
        // repo never carries a guessable credential. Falls back to "password"
        // only for local / testing.
        $env = app()->environment();
        $envPassword = env('STAGING_DEMO_PASSWORD');
        if (in_array($env, ['staging', 'production'], true) && ! $envPassword) {
            throw new \RuntimeException(
                'STAGING_DEMO_PASSWORD env var must be set when seeding in '.$env.' environment.',
            );
        }
        $demoPassword = $envPassword ?: 'password';

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'property_id' => $property?->id,
                'name' => 'Hotel Admin',
                'password' => Hash::make($demoPassword),
                'locale' => 'ka',
                'active' => true,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles([RolesAndPermissionsSeeder::ROLE_SUPER_ADMIN]);

        $reception = User::firstOrCreate(
            ['email' => 'reception@example.test'],
            [
                'property_id' => $property?->id,
                'name' => 'Reception One',
                'password' => Hash::make($demoPassword),
                'locale' => 'ka',
                'active' => true,
                'email_verified_at' => now(),
            ],
        );
        $reception->syncRoles([RolesAndPermissionsSeeder::ROLE_RECEPTION]);
    }
}
