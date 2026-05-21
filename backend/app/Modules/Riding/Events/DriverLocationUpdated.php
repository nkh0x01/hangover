<?php

declare(strict_types=1);

namespace App\Modules\Riding\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts a single GPS sample to the customer subscribed to a ride.
 * Throttled at the source (DriverApp publishes ≤1 Hz during a trip).
 */
final class DriverLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $rideUlid,
        public readonly float $lat,
        public readonly float $lng,
        public readonly int $heading,
        public readonly float $speedKmh,
        public readonly \DateTimeInterface $at,
    ) {}

    public function broadcastAs(): string
    {
        return 'driver.location';
    }

    public function broadcastWith(): array
    {
        return [
            'v' => 1,
            'ride_ulid' => $this->rideUlid,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'heading' => $this->heading,
            'speed_kmh' => round($this->speedKmh, 1),
            'at' => $this->at->format(DATE_ATOM),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("private-ride.{$this->rideUlid}")];
    }
}
