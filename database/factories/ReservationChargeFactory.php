<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationCharge>
 */
class ReservationChargeFactory extends Factory
{
    public function definition(): array
    {
        $qty   = 1;
        $price = fake()->randomFloat(2, 5, 100);

        return [
            'reservation_id' => Reservation::factory(),
            'type' => ReservationCharge::TYPE_FEE,
            'description' => fake()->words(3, true),
            'quantity' => $qty,
            'unit_price' => $price,
            'total' => round($qty * $price, 2),
            'taxable' => true,
            'tax_rate' => 18,
            'added_at' => now(),
        ];
    }
}
