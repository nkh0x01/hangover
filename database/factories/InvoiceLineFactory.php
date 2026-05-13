<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        $qty   = 1;
        $price = fake()->randomFloat(2, 20, 300);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->words(3, true),
            'quantity' => $qty,
            'unit_price' => $price,
            'total' => round($qty * $price, 2),
            'tax_rate' => 18,
        ];
    }
}
