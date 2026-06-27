<?php

declare(strict_types=1);

namespace Database\Factories\Erp;

use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'variant_sku' => strtoupper($this->faker->unique()->bothify('VAR-#####')),
            'barcode' => $this->faker->ean13(),
            'model_compat' => null,
            'is_active' => true,
        ];
    }
}
