<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelRateMapping>
 */
class ChannelRateMappingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'room_type_id' => RoomType::factory(),
            'rate_plan_id' => null,
            'external_rate_id' => 'RATE-'.fake()->unique()->numerify('######'),
            'external_rate_name' => fake()->randomElement(['BAR', 'Non-Refundable', 'Long Stay']),
            'markup_percent' => fake()->randomElement([null, 0, 5, 10, 15]),
            'markup_abs' => null,
        ];
    }
}
