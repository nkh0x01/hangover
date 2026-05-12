<?php

declare(strict_types=1);

namespace App\Modules\Riding\Jobs;

use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Services\DispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-enter the dispatch loop after a rejection, a timeout, or a
 * no-candidates retry. Idempotent — re-runs harmlessly if the ride has
 * already advanced.
 */
final class OfferRideToNextDriver implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public readonly int $rideId) {}

    public function handle(DispatchService $dispatch): void
    {
        $ride = Ride::query()->find($this->rideId);
        if (! $ride) {
            return;
        }
        try {
            $dispatch->dispatchTick($ride);
        } catch (\Throwable $e) {
            Log::channel('dispatch')->error('OfferRideToNextDriver failed', [
                'ride_id' => $this->rideId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
