<?php

use App\Jobs\DailyWhatsAppSummary;
use App\Jobs\FollowUpAbandonedChat;
use App\Jobs\RollupAnalytics;
use App\Models\Conversation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Cron-driven queue worker (cPanel / shared hosting)
|--------------------------------------------------------------------------
| When QUEUE_CONNECTION=database (the cPanel default), we can't run a
| long-lived `queue:work` daemon. Instead we let the scheduler drain
| the queue once a minute. Each invocation runs for up to 58s, then
| exits cleanly so the next minute's tick can take over.
|
| If QUEUE_CONNECTION=redis is set, this is a no-op — start a real
| daemon via supervisor instead.
*/
Schedule::command('queue:work --stop-when-empty --max-time=58 --tries=3 --queue=inbound,reply,comments,default')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->skip(fn () => config('queue.default') !== 'database')
    ->runInBackground();

// Hourly analytics rollups.
Schedule::job(new RollupAnalytics)->hourlyAt(5);

// Legacy catalog sync (kept for non-Woo sources).
Schedule::command('catalog:sync')
    ->cron('*/' . (int) config('catalog.sync_interval_min', 15) . ' * * * *')
    ->skip(fn () => ! empty(config('gadget.consumer_key')));

// gadget.ge product mirror (new Laravel catalog API).
Schedule::command('gadget:sync-products')
    ->cron('*/' . (int) config('gadget.sync.products_minutes', 15) . ' * * * *')
    ->skip(fn () => empty(config('gadget.api.token')))
    ->withoutOverlapping(30);

// gadget.ge coupons / promos.
Schedule::command('gadget:sync-coupons')
    ->cron('*/' . (int) config('gadget.sync.coupons_minutes', 30) . ' * * * *')
    ->skip(fn () => empty(config('gadget.consumer_key')))
    ->withoutOverlapping(30);

// Daily summary at 21:30 Tbilisi time.
Schedule::job(new DailyWhatsAppSummary)
    ->dailyAt('21:30')
    ->timezone(config('app.timezone'));

// Abandoned-chat sweep every 10 min.
Schedule::call(function () {
    $cutoff = now()->subMinutes((int) config('chatbot.follow_up.delay_minutes', 90));
    Conversation::query()
        ->whereIn('lead_status', [Conversation::STATUS_PRODUCT_RECOMMENDED, Conversation::STATUS_INTERESTED])
        ->whereNull('last_followup_at')
        ->where('escalated', false)
        ->where('ai_paused', false)
        ->where('last_inbound_at', '<', $cutoff)
        ->limit(200)
        ->get()
        ->each(fn ($c) => FollowUpAbandonedChat::dispatch($c->id));
})->everyTenMinutes()->name('follow_up_sweep');
