<?php

declare(strict_types=1);

namespace Database\Factories\Erp;

use App\Modules\Erp\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-#####')),
            'name_ka' => 'პროდუქტი '.$this->faker->word(),
            'name_en' => $this->faker->words(2, true),
            'vat_applicable' => true,
            'unit' => 'pcs',
            'is_serialized' => false,
            'cost' => 0,
            'is_active' => true,
        ];
    }

    public function serialized(): self
    {
        return $this->state(['is_serialized' => true]);
    }
}
