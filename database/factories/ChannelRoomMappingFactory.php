<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelRoomMapping>
 */
class ChannelRoomMappingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'room_type_id' => RoomType::factory(),
            'room_id' => null,
            'external_room_id' => 'EXT-'.fake()->unique()->numerify('######'),
            'external_room_name' => fake()->randomElement(['Standard Double', 'Deluxe King', 'Family Suite']),
        ];
    }
}
