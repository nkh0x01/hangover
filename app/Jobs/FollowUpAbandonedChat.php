<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Channels\ChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Dispatched by a scheduled command. If a conversation reached
 * "product_recommended" but went quiet, nudge once.
 */
class FollowUpAbandonedChat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $conversationId) {}

    public function handle(ChannelManager $channels): void
    {
        if (! config('chatbot.follow_up.enabled', true)) {
            return;
        }

        $c = Conversation::with('customer')->find($this->conversationId);
        if (! $c || $c->escalated || $c->ai_paused) {
            return;
        }
        if ($c->last_followup_at) {
            return; // already nudged once
        }
        if ($c->last_inbound_at && $c->last_inbound_at->gt(now()->subMinutes(config('chatbot.follow_up.delay_minutes', 90)))) {
            return;
        }

        // Skip during quiet hours.
        [$qh1, $qh2] = config('chatbot.follow_up.quiet_hours', [23, 9]);
        $h = (int) now()->hour;
        if ($qh1 < $qh2) {
            if ($h >= $qh1 && $h < $qh2) return;
        } else {
            if ($h >= $qh1 || $h < $qh2) return;
        }

        $text = "კიდევ ერთხელ გამარჯობა 👋 ვამოწმებ — გადაწყვიტეთ რა გაინტერესებთ? " .
                "თუ რამე კითხვა გაქვთ, აქ ვარ.";

        $driver = $channels->driver($c->platform);
        $driver->sendText($c->thread_id, $text);

        $c->update(['last_followup_at' => now(), 'last_outbound_at' => now()]);
    }
}
