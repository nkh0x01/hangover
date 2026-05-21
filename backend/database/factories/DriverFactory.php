<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Driver\Models\Driver;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
final class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'city_id' => City::factory(),
            'status' => 'approved',
            'online' => true,
            'online_since' => now(),
            'rating_avg' => 4.85,
            'rating_count' => 100,
            'trips_completed' => 200,
        ];
    }
}
