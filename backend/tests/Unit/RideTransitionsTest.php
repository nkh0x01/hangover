<?php

declare(strict_types=1);

use App\Modules\Riding\StateMachine\RideStatus;
use App\Modules\Riding\StateMachine\Transitions;

it('allows the canonical happy-path transitions', function (): void {
    $path = [
        [RideStatus::Requested, RideStatus::Searching],
        [RideStatus::Searching, RideStatus::Offered],
        [RideStatus::Offered, RideStatus::Accepted],
        [RideStatus::Accepted, RideStatus::DriverArriving],
        [RideStatus::DriverArriving, RideStatus::DriverArrived],
        [RideStatus::DriverArrived, RideStatus::InProgress],
        [RideStatus::InProgress, RideStatus::Completed],
    ];

    foreach ($path as [$from, $to]) {
        expect(Transitions::isAllowed($from, $to))->toBeTrue("$from->value -> $to->value");
    }
});

it('refuses backwards transitions from in_progress', function (): void {
    expect(Transitions::isAllowed(RideStatus::InProgress, RideStatus::DriverArriving))->toBeFalse();
    expect(Transitions::isAllowed(RideStatus::Completed, RideStatus::InProgress))->toBeFalse();
});

it('allows cancellation from any non-terminal state', function (): void {
    foreach ([RideStatus::Requested, RideStatus::Searching, RideStatus::Offered, RideStatus::Accepted, RideStatus::DriverArriving, RideStatus::DriverArrived, RideStatus::InProgress] as $from) {
        expect(Transitions::isAllowed($from, RideStatus::Cancelled))->toBeTrue($from->value);
    }
});

it('marks terminal states correctly', function (): void {
    expect(RideStatus::Completed->isTerminal())->toBeTrue();
    expect(RideStatus::Cancelled->isTerminal())->toBeTrue();
    expect(RideStatus::Searching->isTerminal())->toBeFalse();
    expect(RideStatus::InProgress->isActive())->toBeTrue();
});
