<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SystemPromptSeeder::class,
            DemoEmployeesSeeder::class,
            DemoProductsSeeder::class,
            DemoCouponsSeeder::class,
        ]);
    }
}
