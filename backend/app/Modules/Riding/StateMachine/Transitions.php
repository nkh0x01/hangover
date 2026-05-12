<?php

declare(strict_types=1);

namespace App\Modules\Riding\StateMachine;

/**
 * Allowed RideStatus transitions. Keys are the *from* state, values are
 * the set of valid *to* states. Mutated only via PRs to this file.
 */
final class Transitions
{
    /**
     * @return array<value-of<RideStatus>, list<value-of<RideStatus>>>
     */
    public static function map(): array
    {
        return [
            RideStatus::Requested->value      => [RideStatus::Searching->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            RideStatus::Searching->value      => [RideStatus::Offered->value, RideStatus::NoDrivers->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            RideStatus::Offered->value        => [RideStatus::Accepted->value, RideStatus::Searching->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            RideStatus::Accepted->value       => [RideStatus::DriverArriving->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            RideStatus::DriverArriving->value => [RideStatus::DriverArrived->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            RideStatus::DriverArrived->value  => [RideStatus::InProgress->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            RideStatus::InProgress->value     => [RideStatus::Completed->value, RideStatus::Cancelled->value, RideStatus::Failed->value],
            // Terminal:
            RideStatus::Completed->value  => [],
            RideStatus::Cancelled->value  => [],
            RideStatus::NoDrivers->value  => [],
            RideStatus::Failed->value     => [],
        ];
    }

    public static function isAllowed(RideStatus $from, RideStatus $to): bool
    {
        return in_array($to->value, self::map()[$from->value] ?? [], true);
    }
}
