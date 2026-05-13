<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelSyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelSyncLog>
 */
class ChannelSyncLogFactory extends Factory
{
    public function definition(): array
    {
        $started = now()->subSeconds(fake()->numberBetween(0, 3600));
        $duration = fake()->numberBetween(50, 4000);

        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'direction' => fake()->randomElement([ChannelSyncLog::DIRECTION_IN, ChannelSyncLog::DIRECTION_OUT]),
            'action' => fake()->randomElement([
                ChannelSyncLog::ACTION_PULL_RESERVATIONS,
                ChannelSyncLog::ACTION_PUSH_AVAILABILITY,
                ChannelSyncLog::ACTION_PUSH_RATES,
                ChannelSyncLog::ACTION_TEST_CONNECTION,
            ]),
            'status' => ChannelSyncLog::STATUS_SUCCESS,
            'payload_summary' => ['items' => fake()->numberBetween(1, 50)],
            'response_summary' => ['ok' => true],
            'error' => null,
            'duration_ms' => $duration,
            'started_at' => $started,
            'finished_at' => $started->copy()->addMilliseconds($duration),
            'triggered_by' => ChannelSyncLog::TRIGGER_MANUAL,
        ];
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => ChannelSyncLog::STATUS_FAILED,
            'error' => 'Mock provider error',
            'response_summary' => null,
        ]);
    }
}
