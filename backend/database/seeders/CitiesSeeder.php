<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        // Tbilisi — center approx 41.7151°N 44.8271°E
        DB::table('cities')->upsert([
            [
                'country_code' => 'GE',
                'name' => 'Tbilisi',
                'slug' => 'tbilisi',
                'timezone' => 'Asia/Tbilisi',
                'default_currency' => 'GEL',
                'default_commission_rate' => 0.20,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['slug']);

        DB::statement(
            'UPDATE cities SET center = ST_SRID(POINT(?, ?), 4326) WHERE slug = ?',
            [44.8271, 41.7151, 'tbilisi'],
        );
    }
}
