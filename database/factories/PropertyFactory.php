<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Hotel';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'timezone' => 'UTC',
            'base_currency' => 'USD',
            'vat_rate_default' => 18,
            'address' => [
                'line1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'country' => fake()->countryCode(),
            ],
            'contact' => [
                'email' => fake()->safeEmail(),
                'phone' => fake()->phoneNumber(),
            ],
            'settings' => [],
            'active' => true,
        ];
    }
}
