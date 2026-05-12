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

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'property_id' => $property?->id,
                'name' => 'Hotel Admin',
                'password' => Hash::make('password'),
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
                'password' => Hash::make('password'),
                'locale' => 'ka',
                'active' => true,
                'email_verified_at' => now(),
            ],
        );
        $reception->syncRoles([RolesAndPermissionsSeeder::ROLE_RECEPTION]);
    }
}
