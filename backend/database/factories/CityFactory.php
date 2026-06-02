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
        return $this->afterCreating(function (City $city): void {
            // POINT lives in MySQL only; SQLite test runs use the
            // sqlite-aware migration which omits the spatial column.
            if (DB::getDriverName() === 'mysql') {
                DB::statement(
                    'UPDATE cities SET center = ST_GeomFromText(CONCAT(\'POINT(\', ?, \' \', ?, \')\'), 4326) WHERE id = ?',
                    [44.8271, 41.7151, $city->id],
                );
            }
        });
    }
}
