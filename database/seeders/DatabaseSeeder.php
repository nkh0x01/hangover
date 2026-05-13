<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PropertySeeder::class,
            AvailabilityCalendarSeeder::class,
            AdminUserSeeder::class,
            InventorySeeder::class,
            PricingSeeder::class,
        ]);
    }
}
