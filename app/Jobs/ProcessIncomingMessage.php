<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The first thing that runs after a webhook is acknowledged. Job:
 *   - upsert customer + conversation
 *   - persist the message
 *   - cancel any pending debounced reply
 *   - re-schedule a fresh debounced reply
 *
 * Deliberately cheap so the queue depth doesn't blow up during a
 * traffic spike. The AI call lives in GenerateAIReply.
 */
class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 8;

    public int $timeout = 30;

    public function __construct(public array $event) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $platform = $this->event['platform'];

            $customer = Customer::firstOrCreate(
                ['platform' => $platform, 'platform_user_id' => $this->event['sender_id']],
                [
                    'display_name' => $this->event['sender_name'] ?? null,
                    'last_seen_at' => now(),
                ],
            );

            if (! $customer->wasRecentlyCreated) {
                $customer->update(['last_seen_at' => now()]);
                if (! $customer->display_name && ! empty($this->event['sender_name'])) {
                    $customer->update(['display_name' => $this->event['sender_name']]);
                }
            }

            if ($customer->is_blocked || $customer->is_spam) {
                return;
            }

            $conversation = Conversation::firstOrCreate(
                ['platform' => $platform, 'thread_id' => $this->event['thread_id']],
                ['customer_id' => $customer->id, 'lead_status' => Conversation::STATUS_NEW],
            );

            // Idempotency: skip if we already saw this platform_msg_id.
            if (! empty($this->event['platform_msg_id']) &&
                Message::where('platform_msg_id', $this->event['platform_msg_id'])->exists()) {
                return;
            }

            Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'platform_msg_id' => $this->event['platform_msg_id'] ?? null,
                'direction' => Message::DIRECTION_IN,
                'kind' => $this->event['kind'] ?? 'text',
                'body' => $this->event['text'] ?? null,
                'media_json' => $this->event['media'] ?? null,
                'raw_json' => $this->event['raw'] ?? null,
                'sent_at' => now()->setTimestamp((int) ($this->event['received_at'] ?? time())),
            ]);

            $conversation->update(['last_inbound_at' => now()]);

            // Cancel any pending reply: mark previous job stale by rotating the token.
            $newJobId = (string) Str::ulid();
            $conversation->update(['pending_reply_job_id' => $newJobId]);

            // Debounced reply. AUTO_REPLY_DELAY_SECONDS (admin setting) wins
            // over the .env-defaulted debounce window. Master toggle / per-
            // channel toggle are checked at job execution time so admins
            // can flip them on/off without flushing the queue.
            $settings = app(\App\Services\SettingsService::class);
            $delaySetting = $settings->getInt('AUTO_REPLY_DELAY_SECONDS', 0);
            if ($delaySetting > 0) {
                $delay = $delaySetting;
            } else {
                $delay = random_int(
                    (int) config('chatbot.debounce.min_seconds', 5),
                    (int) config('chatbot.debounce.max_seconds', 15),
                );
            }

            GenerateAIReply::dispatch($conversation->id, $newJobId)
                ->onQueue('reply')
                ->delay(now()->addSeconds($delay));

            // Dedicated auto-reply log entry: "scheduled"
            try {
                app(\App\Services\AI\AutoReplySender::class)->scheduledLog(
                    $conversation, $delay, $newJobId
                );
            } catch (\Throwable $e) {
                // logging should never fail the main pipeline
            }

            AuditLog::record('system', 'message.in', 'conversation', $conversation->id, [
                'platform_msg_id' => $this->event['platform_msg_id'] ?? null,
                'kind' => $this->event['kind'] ?? 'text',
                'len' => mb_strlen((string) ($this->event['text'] ?? '')),
                'debounce_s' => $delay,
            ]);
        });
    }
}
