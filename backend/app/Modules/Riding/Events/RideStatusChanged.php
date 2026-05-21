<?php

declare(strict_types=1);

namespace App\Modules\Riding\Events;

use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\StateMachine\RideStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted on every ride state transition. Listeners route to the
 * customer's and driver's private channels.
 */
final class RideStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $rideUlid,
        public readonly RideStatus $from,
        public readonly RideStatus $to,
        public readonly string $actorType = 'system',
        public readonly ?int $actorId = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'ride.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'v' => 1,
            'ride_ulid' => $this->rideUlid,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'at' => now()->toIso8601String(),
            'actor' => $this->actorType,
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("private-ride.{$this->rideUlid}")];
    }

    public static function fromRide(Ride $ride, RideStatus $from, RideStatus $to, string $actorType = 'system', ?int $actorId = null): self
    {
        return new self($ride->ulid, $from, $to, $actorType, $actorId);
    }
}
