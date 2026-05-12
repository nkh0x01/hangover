<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 100, 2000);

        return [
            'property_id' => Property::factory(),
            'number' => 'INV-'.now()->year.'-'.strtoupper(Str::random(6)),
            'reservation_id' => function (array $attrs) {
                return Reservation::factory()->create(['property_id' => $attrs['property_id']])->id;
            },
            'issued_at' => now(),
            'subtotal' => $total,
            'tax_total' => round($total * 0.18, 2),
            'discount_total' => 0,
            'total' => $total,
            'paid_total' => 0,
            'balance' => $total,
            'currency' => 'USD',
            'status' => Invoice::STATUS_DRAFT,
        ];
    }
}
