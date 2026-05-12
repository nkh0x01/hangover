<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationStatusHistory>
 */
class ReservationStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'from_status' => Reservation::STATUS_PENDING,
            'to_status' => Reservation::STATUS_CONFIRMED,
            'note' => null,
            'changed_at' => now(),
        ];
    }
}
