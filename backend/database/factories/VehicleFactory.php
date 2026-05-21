<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
final class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'type' => 'scooter_electric',
            'brand' => 'Xiaomi',
            'model' => 'Mi Electric Scooter Pro 2',
            'plate' => strtoupper($this->faker->bothify('??-####')),
            'color' => 'Black',
            'year' => 2024,
            'is_active' => true,
            'telemetry_supported' => false,
        ];
    }
}
