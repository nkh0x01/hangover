<?php

namespace Database\Factories;

use App\Models\DailyRoomPrice;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyRoomPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_type_id' => RoomType::factory(),
            'room_id' => null,
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'price' => fake()->randomFloat(2, 80, 300),
            'min_stay' => null,
            'max_stay' => null,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'source' => DailyRoomPrice::SOURCE_MANUAL,
        ];
    }
}
