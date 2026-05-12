<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Standard', 'Deluxe', 'Suite', 'Family', 'Twin', 'King']);

        return [
            'property_id' => Property::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'base_price' => fake()->randomFloat(2, 50, 400),
            'capacity_adults' => fake()->numberBetween(1, 4),
            'capacity_children' => fake()->numberBetween(0, 2),
            'max_occupancy' => fake()->numberBetween(2, 5),
            'bed_type' => fake()->randomElement(['single', 'double', 'queen', 'king', 'twin']),
            'size_sqm' => fake()->numberBetween(15, 60),
            'description' => fake()->sentence(),
            'default_check_in_time' => '14:00:00',
            'default_check_out_time' => '11:00:00',
        ];
    }
}
