<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RollupAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AnalyticsService $analytics): void
    {
        $hour = CarbonImmutable::now()->subHour();
        $analytics->rollupHour($hour);
    }
}
