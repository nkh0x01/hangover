<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_type_id' => function (array $attrs) {
                return RoomType::factory()->create(['property_id' => $attrs['property_id']])->id;
            },
            'number' => (string) fake()->unique()->numberBetween(100, 999),
            'floor' => fake()->numberBetween(1, 4),
            'status' => Room::STATUS_AVAILABLE,
            'notes' => null,
        ];
    }

    public function available(): static
    {
        return $this->state(fn () => ['status' => Room::STATUS_AVAILABLE]);
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => Room::STATUS_OCCUPIED]);
    }

    public function dirty(): static
    {
        return $this->state(fn () => ['status' => Room::STATUS_DIRTY]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['status' => Room::STATUS_MAINTENANCE]);
    }
}
