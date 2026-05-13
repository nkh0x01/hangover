<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'reservation_id' => function (array $attrs) {
                return Reservation::factory()->create(['property_id' => $attrs['property_id']])->id;
            },
            'method' => Payment::METHOD_CASH,
            'amount' => fake()->randomFloat(2, 20, 500),
            'currency' => 'USD',
            'status' => Payment::STATUS_COMPLETED,
            'reference' => null,
            'paid_at' => now(),
        ];
    }
}
