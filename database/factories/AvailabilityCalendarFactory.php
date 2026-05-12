<?php

namespace Database\Factories;

use App\Models\AvailabilityCalendar;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AvailabilityCalendar>
 */
class AvailabilityCalendarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_id' => function (array $attrs) {
                return Room::factory()->create(['property_id' => $attrs['property_id']])->id;
            },
            'date' => fake()->dateTimeBetween('today', '+60 days')->format('Y-m-d'),
            'status' => AvailabilityCalendar::STATUS_OPEN,
            'reservation_id' => null,
        ];
    }

    public function booked(): static
    {
        return $this->state(fn () => ['status' => AvailabilityCalendar::STATUS_BOOKED]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'status' => AvailabilityCalendar::STATUS_BLOCKED,
            'blocked_reason' => 'Owner block',
        ]);
    }
}
