<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Driver\Models\DriverApplication;
use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverApplication>
 */
final class DriverApplicationFactory extends Factory
{
    protected $model = DriverApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'city_id' => City::factory(),
            'status' => 'draft',
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'personal_id' => $this->faker->numerify('###########'),
            'phone_e164' => '+9955'.$this->faker->numerify('########'),
            'email' => $this->faker->safeEmail(),
            'service_zone' => 'Tbilisi',
            'driver_type' => 'moto',
            'vehicle_type' => 'scooter_petrol',
            'vehicle_brand' => 'Honda',
            'vehicle_model' => 'PCX',
            'vehicle_year' => 2023,
            'vehicle_color' => 'Black',
            'vehicle_plate' => strtoupper($this->faker->bothify('??-###')),
            'information_confirmed' => true,
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Documents are unclear.'): self
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
