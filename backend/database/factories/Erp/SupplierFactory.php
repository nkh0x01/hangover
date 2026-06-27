<?php

declare(strict_types=1);

namespace Database\Factories\Erp;

use App\Modules\Erp\Procurement\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
final class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'tax_id' => (string) $this->faker->numberBetween(100000000, 999999999),
            'phone' => '+9955'.$this->faker->numerify('########'),
            'email' => $this->faker->safeEmail(),
            'payment_terms_days' => $this->faker->randomElement([0, 14, 30]),
            'is_active' => true,
        ];
    }
}
