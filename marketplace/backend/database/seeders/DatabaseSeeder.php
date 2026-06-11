<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UsersSeeder::class,
            CategoriesSeeder::class,
            SellersSeeder::class,
            ProductsSeeder::class,
            FundingProgramsSeeder::class,
            CmsSeeder::class,
        ]);
    }
}
