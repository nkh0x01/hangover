<?php

declare(strict_types=1);

namespace App\Modules\Riding\StateMachine;

enum RideStatus: string
{
    case Requested = 'requested';
    case Searching = 'searching';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case DriverArriving = 'driver_arriving';
    case DriverArrived = 'driver_arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoDrivers = 'no_drivers';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoDrivers, self::Failed], true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }
}
