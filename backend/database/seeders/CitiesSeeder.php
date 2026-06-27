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
        $tbilisi = [
            'country_code' => 'GE',
            'name' => 'Tbilisi',
            'slug' => 'tbilisi',
            'timezone' => 'Asia/Tbilisi',
            'default_currency' => 'GEL',
            'default_commission_rate' => 0.20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // `center` is a MySQL-only spatial column (POINT NOT NULL); supply it in
        // the insert itself rather than a follow-up UPDATE, which would fail the
        // NOT NULL check first.
        if (DB::getDriverName() === 'mysql') {
            $tbilisi['center'] = DB::raw("ST_GeomFromText('POINT(44.8271 41.7151)', 4326)");
        }

        DB::table('cities')->upsert(
            [$tbilisi],
            ['slug'],
            ['name', 'timezone', 'default_currency', 'default_commission_rate', 'is_active', 'updated_at'],
        );
    }
}
