<?php

declare(strict_types=1);

namespace App\Modules\Riding\Actions;

use App\Modules\Driver\Models\Driver;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\RideStateMachine;
use App\Modules\Riding\StateMachine\RideStatus;

final readonly class StartTrip
{
    public function __construct(private RideStateMachine $stateMachine) {}

    public function execute(Driver $driver, Ride $ride): Ride
    {
        abort_unless($ride->driver_id === $driver->id, 403, 'auth.forbidden');

        return $this->stateMachine->transition(
            ride: $ride,
            to: RideStatus::InProgress,
            actorType: 'driver',
            actorId: $driver->user_id,
        );
    }
}
