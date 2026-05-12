<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = Carbon::today()->addDays(fake()->numberBetween(1, 30));
        $nights  = fake()->numberBetween(1, 5);
        $checkOut = $checkIn->copy()->addDays($nights);

        return [
            'code' => 'R-'.strtoupper(Str::random(8)),
            'property_id' => Property::factory(),
            'guest_id' => function (array $attrs) {
                return Guest::factory()->create(['property_id' => $attrs['property_id']])->id;
            },
            'room_id' => function (array $attrs) {
                return Room::factory()->create(['property_id' => $attrs['property_id']])->id;
            },
            'room_type_id' => function (array $attrs) {
                return Room::find($attrs['room_id'])->room_type_id;
            },
            'check_in_date' => $checkIn->toDateString(),
            'check_out_date' => $checkOut->toDateString(),
            'nights' => $nights,
            'adults' => fake()->numberBetween(1, 2),
            'children' => 0,
            'source' => Reservation::SOURCE_DIRECT,
            'status' => Reservation::STATUS_CONFIRMED,
            'payment_status' => Reservation::PAYMENT_UNPAID,
            'room_rate_total' => 0,
            'extras_total' => 0,
            'taxes_total' => 0,
            'discount_total' => 0,
            'grand_total' => 0,
            'paid_total' => 0,
            'currency' => 'USD',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Reservation::STATUS_PENDING]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => Reservation::STATUS_CONFIRMED]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn () => [
            'status' => Reservation::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => [
            'status' => Reservation::STATUS_CHECKED_OUT,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
