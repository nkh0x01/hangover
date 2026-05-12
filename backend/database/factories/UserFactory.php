<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Support\Ulid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'ulid' => Ulid::new(),
            'type' => 'customer',
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'phone_e164' => '+9955'.$this->faker->numerify('########'),
            'phone_verified_at' => now(),
            'email' => $this->faker->unique()->safeEmail(),
            'locale' => 'ka',
            'status' => 'active',
            'referral_code' => strtoupper(substr(Ulid::new(), -8)),
        ];
    }

    public function admin(): self
    {
        return $this->state(fn () => ['type' => 'admin', 'email' => $this->faker->unique()->safeEmail()]);
    }

    public function driver(): self
    {
        return $this->state(fn () => ['type' => 'driver']);
    }
}
