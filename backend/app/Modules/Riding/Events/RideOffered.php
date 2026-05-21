<?php

declare(strict_types=1);

namespace App\Modules\Riding\Events;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Models\Ride;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted when DispatchService offers a ride to a specific driver.
 * Targets the driver's own private channel so only they receive the
 * payload (other drivers don't even know the ride exists).
 */
final class RideOffered implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $rideUlid,
        public readonly string $driverUlid,
        public readonly array $payload,
    ) {}

    public function broadcastAs(): string
    {
        return 'ride.offered';
    }

    public function broadcastWith(): array
    {
        return ['v' => 1] + $this->payload;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("private-driver.{$this->driverUlid}")];
    }

    public static function build(Ride $ride, Driver $driver, int $distanceM, \DateTimeInterface $expiresAt): self
    {
        $driver->loadMissing('user');

        return new self($ride->ulid, $driver->user->ulid, [
            'ride_ulid' => $ride->ulid,
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'pickup' => [
                'address' => $ride->pickup_address,
            ],
            'dropoff' => [
                'address' => $ride->dropoff_address,
            ],
            'distance_to_pickup_m' => $distanceM,
            'fare' => [
                'amount' => (float) $ride->quoted_amount,
                'currency' => $ride->currency,
            ],
        ]);
    }
}
