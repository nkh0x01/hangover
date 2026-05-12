<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;

final readonly class CancelRide
{
    public function __construct(private RideStateMachine $stateMachine) {}

    public function execute(User $actor, Ride $ride, string $reason): Ride
    {
        $actorType = $this->actorType($actor, $ride);
        $reasonCode = $this->normaliseReason($actorType, $reason);

        $ride->cancellation_reason = $reasonCode;
        $ride->cancellation_by_user_id = $actor->id;
        $ride->save();

        return $this->stateMachine->transition(
            ride: $ride,
            to: RideStatus::Cancelled,
            actorType: $actorType,
            actorId: $actor->id,
            reason: $reasonCode,
        );
    }

    private function actorType(User $actor, Ride $ride): string
    {
        if ($actor->id === $ride->customer_id) {
            return 'customer';
        }
        if ($ride->driver && $ride->driver->user_id === $actor->id) {
            return 'driver';
        }

        return 'admin';
    }

    private function normaliseReason(string $actorType, string $reason): string
    {
        return match ($actorType) {
            'customer' => 'customer_cancelled',
            'driver'   => 'driver_cancelled',
            'admin'    => 'admin_cancelled',
            default    => 'customer_cancelled',
        };
    }
}
