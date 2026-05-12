<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Guest>
 */
class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'country' => fake()->countryCode(),
            'language' => 'en',
            'doc_type' => fake()->randomElement(['passport', 'id_card']),
            'doc_number' => strtoupper(fake()->bothify('??######')),
            'doc_country' => fake()->countryCode(),
            'dob' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'vip' => false,
            'blacklisted' => false,
            'marketing_opt_in' => false,
        ];
    }

    public function vip(): static
    {
        return $this->state(fn () => ['vip' => true]);
    }
}
