<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Coca-Cola 330ml', 'Sprite 330ml', 'Borjomi 500ml', 'Snickers', 'Lays', 'Beer 500ml', 'Mineral Water 1L']);
        $sale = fake()->randomFloat(2, 2, 15);

        return [
            'property_id' => Property::factory(),
            'category_id' => null,
            'name' => $name,
            'sku' => strtoupper(Str::random(6)),
            'barcode' => fake()->ean13(),
            'cost_price' => round($sale * 0.6, 2),
            'sale_price' => $sale,
            'tax_rate' => 0,
            'track_stock' => true,
            'low_stock_threshold' => 5,
            'active' => true,
        ];
    }
}
