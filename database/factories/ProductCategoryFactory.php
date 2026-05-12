<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Drinks', 'Snacks', 'Spirits', 'Personal Care']);

        return [
            'property_id' => Property::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
