<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Geo\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<City>
 */
final class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'country_code' => 'GE',
            'name' => 'Tbilisi',
            'slug' => 'tbilisi-'.$this->faker->unique()->numerify('####'),
            'timezone' => 'Asia/Tbilisi',
            'default_currency' => 'GEL',
            'default_commission_rate' => 0.20,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (City $city): void {
            // `center` is a MySQL-only spatial column (SQLite omits it). It is
            // POINT NOT NULL with a SPATIAL INDEX, so it must be supplied at
            // insert time — a post-insert UPDATE can't work because the insert
            // itself would violate NOT NULL first.
            if (DB::getDriverName() === 'mysql') {
                $city->setAttribute(
                    'center',
                    DB::raw("ST_GeomFromText('POINT(44.8271 41.7151)', 4326)"),
                );
            }
        });
    }
}
