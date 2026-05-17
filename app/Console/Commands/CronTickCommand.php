<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Single-entry cron tick for cPanel / shared hosting.
 *
 * cPanel allows one minute as the smallest cron interval, and most
 * shared hosting plans don't let you run multiple cron entries
 * reliably. This command runs:
 *   1. `schedule:run` — Laravel's normal scheduler
 *   2. drains the database queue for up to ~50 seconds
 *
 * Recommended cPanel cron entry (every minute):
 *
 *   * * * * * cd /home/USER/bot.gadget.ge && /usr/local/bin/php artisan tick >> storage/logs/tick.log 2>&1
 *
 * If you already use supervisor + redis, prefer the standard
 * `schedule:run` cron + `queue:work` daemon and leave this command
 * alone.
 */
class CronTickCommand extends Command
{
    protected $signature = 'tick {--max-time=50 : Max seconds to spend draining the queue}';

    protected $description = 'One-shot cron tick: runs the scheduler and drains the database queue. Built for cPanel.';

    public function handle(): int
    {
        $start = microtime(true);

        $this->call('schedule:run');

        // Only drain the queue ourselves if database driver — redis
        // setups have a real daemon.
        if (config('queue.default') === 'database') {
            $budget = max(5, (int) $this->option('max-time'));
            $this->call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => $budget,
                '--tries' => 3,
                '--queue' => 'inbound,reply,comments,default',
            ]);
        }

        $elapsed = round(microtime(true) - $start, 1);
        $this->info("tick: done in {$elapsed}s");

        return self::SUCCESS;
    }
}
