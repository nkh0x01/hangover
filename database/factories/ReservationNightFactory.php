<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationNight>
 */
class ReservationNightFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'room_id' => Room::factory(),
            'nightly_rate' => fake()->randomFloat(2, 50, 400),
            'currency' => 'USD',
        ];
    }
}
