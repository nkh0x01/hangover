<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsService;
use App\Services\Channels\ChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled daily — sends a one-page WhatsApp summary to the owner(s).
 */
class DailyWhatsAppSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AnalyticsService $analytics, ChannelManager $channels): void
    {
        $d = $analytics->dashboard();

        $body = "📊 დღის შეჯამება ({$d['date']})\n\n" .
            "• ჩატები: {$d['total_conversations']}\n" .
            "• შემოსული შეტყობინებები: {$d['inbound']}\n" .
            "• AI-ის პასუხები: {$d['ai_replies']}\n" .
            "• თანამშრომლის პასუხები: {$d['human_replies']}\n" .
            "• ესკალაცია: {$d['escalations']}\n" .
            "• შეკვეთა გახსნილი: {$d['orders_created']}\n" .
            "• გადახდილი შეკვეთა: {$d['orders_paid']}\n" .
            "• კომენტარები: {$d['comments_handled']}\n" .
            '• AI წილი პასუხებში: ' . round(($d['ai_share'] ?? 0) * 100) . '%';

        $driver = $channels->driver('whatsapp');
        foreach (config('escalation.whatsapp_targets', []) as $phone) {
            $driver->sendText($phone, $body);
        }
    }
}
