<?php

declare(strict_types=1);

namespace App\Modules\Riding\Jobs;

use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\DispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The first dispatch tick for a freshly-created ride. Subsequent ticks
 * arrive as OfferRideToNextDriver from the rejection / timeout paths.
 */
final class DispatchRide implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public readonly int $rideId) {}

    public function uniqueId(): string
    {
        return 'dispatch:ride:'.$this->rideId;
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function handle(DispatchService $dispatch): void
    {
        $ride = Ride::query()->find($this->rideId);
        if (! $ride) {
            return;
        }
        $dispatch->dispatchTick($ride);
    }
}
