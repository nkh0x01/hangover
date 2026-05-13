<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelConnection>
 */
class ChannelConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'channel' => ChannelConnection::CHANNEL_MOCK,
            'name' => fake()->randomElement(['Mock OTA', 'Test Channel', 'Sandbox Booking']),
            'status' => ChannelConnection::STATUS_ACTIVE,
            'credentials' => ['token' => fake()->uuid()],
            'settings' => ['currency' => 'GEL', 'timezone' => 'Asia/Tbilisi'],
            'last_pull_at' => null,
            'last_push_at' => null,
            'last_error' => null,
            'error_count' => 0,
        ];
    }

    public function paused(): self
    {
        return $this->state(fn () => ['status' => ChannelConnection::STATUS_PAUSED]);
    }

    public function errored(): self
    {
        return $this->state(fn () => [
            'status' => ChannelConnection::STATUS_ERROR,
            'last_error' => 'Mock failure',
            'error_count' => 3,
        ]);
    }

    public function booking(): self
    {
        return $this->state(fn () => [
            'channel' => ChannelConnection::CHANNEL_BOOKING,
            'name' => 'Booking.com (stub)',
        ]);
    }
}
