<?php

namespace App\Services\Escalation;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Escalation;
use App\Models\Message;
use App\Services\Channels\ChannelManager;
use Illuminate\Support\Facades\Log;

class EscalationDispatcher
{
    public function __construct(private ChannelManager $channels) {}

    public function dispatch(
        Conversation $conversation,
        Customer $customer,
        string $reason,
        string $urgency = 'medium',
        ?string $summary = null,
    ): Escalation {
        $escalation = Escalation::create([
            'conversation_id' => $conversation->id,
            'customer_id'     => $customer->id,
            'reason'          => $reason,
            'urgency'         => $urgency,
            'summary'         => $summary,
        ]);

        $conversation->update([
            'escalated'         => true,
            'escalation_reason' => $reason,
            'lead_status'       => Conversation::STATUS_ESCALATED,
            'ai_paused'         => (bool) config('escalation.pause_ai_after_escalation', true),
        ]);

        $this->notifyOwner($escalation, $conversation, $customer);

        return $escalation;
    }

    private function notifyOwner(Escalation $e, Conversation $c, Customer $customer): void
    {
        $targets = config('escalation.whatsapp_targets', []);
        if (empty($targets)) {
            Log::warning('escalation.no_targets', ['conversation' => $c->id]);
            return;
        }

        $body = $this->renderNotification($e, $c, $customer);

        $driver = $this->channels->driver('whatsapp');
        foreach ($targets as $i => $phone) {
            $result = $driver->sendText($phone, $body);
            $e->update(['notified_to' => $phone]);

            if (! $result->ok) {
                Log::warning('escalation.notify.failed', ['phone' => $phone, 'err' => $result->error]);
            }

            if ($i === 0) {
                // Primary only — secondary numbers paged later by SecondaryEscalationPing job.
                break;
            }
        }
    }

    private function renderNotification(Escalation $e, Conversation $c, Customer $customer): string
    {
        $last = Message::where('conversation_id', $c->id)
            ->where('direction', Message::DIRECTION_IN)
            ->latest('id')->first();

        $emoji = match ($e->urgency) {
            'high'   => '🚨',
            'medium' => '⚠️',
            default  => 'ℹ️',
        };

        $link = rtrim(config('escalation.admin_url', ''), '/') . '/c/' . $c->id;
        $name = $customer->display_name ?: ('id:' . $customer->platform_user_id);
        $platform = strtoupper($c->platform);

        $lastLine = $last ? '"' . mb_substr($last->body ?? '', 0, 220) . '"' : '(no message)';

        $reasonLabel = $e->reason . ' / ' . $e->urgency;

        return "$emoji [$platform] $name\n" .
               "$lastLine\n\n" .
               "Why: $reasonLabel\n" .
               ($e->summary ? "Note: " . mb_substr($e->summary, 0, 240) . "\n" : '') .
               "Open: $link";
    }
}
