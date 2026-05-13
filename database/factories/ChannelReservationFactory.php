<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelReservation>
 */
class ChannelReservationFactory extends Factory
{
    public function definition(): array
    {
        $payload = [
            'guest' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => fake()->safeEmail(),
            ],
            'check_in' => fake()->date(),
            'check_out' => fake()->date(),
            'adults' => fake()->numberBetween(1, 3),
            'children' => 0,
            'external_room_id' => 'EXT-'.fake()->numerify('######'),
            'total' => fake()->randomFloat(2, 80, 600),
            'currency' => 'GEL',
        ];

        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'external_id' => 'BK-'.fake()->unique()->numerify('########'),
            'hash' => hash('sha256', json_encode($payload)),
            'raw_payload' => $payload,
            'reservation_id' => null,
            'status' => ChannelReservation::STATUS_RECEIVED,
            'received_at' => now(),
            'processed_at' => null,
            'error' => null,
        ];
    }
}
