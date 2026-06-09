<?php

namespace App\Services\AI;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Channels\ChannelManager;
use App\Services\Gadget\ProductSearchService;
use App\Services\Gadget\WooCommerceClient;
use App\Services\SettingsService;
use Throwable;

/**
 * Owns the "actually send the bot's reply" side of the auto-reply
 * pipeline. Given an AiSuggestionService result, decides whether to:
 *   - send image + text (if WC-grounded product reply)
 *   - send text only (general reply or fallback line)
 *   - skip entirely (validator rejected etc.)
 *
 * Persists outbound Message rows with is_ai=true. Writes a dedicated
 * audit log line per send/skip so /admin/inbox can show a status.
 */
class AutoReplySender
{
    private const LOG_FILE = 'logs/auto-reply.log';

    public function __construct(
        private ChannelManager $channels,
        private ProductSearchService $products,
        private WooCommerceClient $wc,
        private SettingsService $settings,
    ) {}

    /**
     * Compute human-like typing delay for the given text. Pulled from
     * config('chatbot.typing'). Default: ~4-12 seconds total.
     *
     * Formula: clamp(min_ms + per_char * len, min_ms, max_ms)
     */
    public function computeTypingDelaySeconds(string $text): int
    {
        $len = mb_strlen($text);
        $min = max(2000, (int) config('chatbot.typing.min_ms', 4000)); // 4s floor
        $per = (int) config('chatbot.typing.per_char_ms', 35);
        $max = max($min + 1000, (int) config('chatbot.typing.max_ms', 12000)); // 12s ceiling
        $ms = $min + ($per * $len);
        // ±15% jitter so successive replies don't look identical
        $jitter = (int) ($ms * (mt_rand(-15, 15) / 100));
        $ms = max($min, min($max, $ms + $jitter));
        return (int) round($ms / 1000);
    }

    /**
     * Guard against duplicate sends. Returns true if an identical-body
     * outbound message has been sent in the last 60s for this conversation.
     */
    private function isDuplicateRecent(Conversation $conv, string $body): bool
    {
        $hash = md5(trim($body));
        return Message::where('conversation_id', $conv->id)
            ->where('direction', Message::DIRECTION_OUT)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->whereRaw('MD5(TRIM(IFNULL(body, "")))=?', [$hash])
            ->exists();
    }

    /**
     * Classify a send error as transient (retry-worthy) or permanent.
     * Returns 'transient' | 'permanent' | 'unknown'.
     */
    private function classifyError(?string $err, ?array $detail = null): string
    {
        if (! $err) return 'unknown';
        $low = strtolower($err);
        // Network / DNS / timeout — retry
        if (str_contains($low, 'getaddrinfo') || str_contains($low, 'curl error 6') ||
            str_contains($low, 'timeout') || str_contains($low, 'connect') ||
            str_contains($low, 'ssl') || str_contains($low, 'temporarily unavailable')) {
            return 'transient';
        }
        // HTTP 5xx — retry
        if (preg_match('/\b5\d\d\b/', $low) || str_contains($low, 'internal server')) {
            return 'transient';
        }
        // Specific permanent Meta errors
        if (str_contains($low, 'cannot send messages to this id') ||
            str_contains($low, 'no matching user') ||
            str_contains($low, 'invalid recipient') ||
            str_contains($low, 'must be a valid id') ||
            str_contains($low, 'does not exist') ||
            str_contains($low, 'message blocked')) {
            return 'permanent';
        }
        // 401/403 — bad token, treat as permanent (admin must fix)
        if (preg_match('/\b40[13]\b/', $low) || str_contains($low, 'unauthor') || str_contains($low, 'permission')) {
            return 'permanent';
        }
        return 'unknown';
    }

    /**
     * @param Conversation $conv
     * @param array $suggestion result from AiSuggestionService::suggest()
     * @return array{ok: bool, action: string, message_id?: int, error?: string}
     */
    public function send(Conversation $conv, array $suggestion): array
    {
        $source = $suggestion['source'] ?? '';
        $text = $suggestion['suggestion'] ?? '';
        $products = $suggestion['products'] ?? [];

        if (! $text) {
            return $this->skip($conv, 'empty_suggestion', $suggestion);
        }

        // ============ Dedup guard ============
        // If we already sent an identical text in the last 60s, skip.
        // Catches the rare race between job retry + manual send.
        if ($this->isDuplicateRecent($conv, $text)) {
            return $this->skip($conv, 'duplicate_recent_send', ['body_hash' => substr(md5($text), 0, 8)]);
        }

        try {
            $driver = $this->channels->driver($conv->platform);
        } catch (Throwable $e) {
            return $this->skip($conv, 'channel_driver_unavailable: '.$e->getMessage(), $suggestion);
        }

        $imageSent = false;
        $product = null;
        $imageUrl = null;

        // WC-grounded product reply → send image of the first cited product
        // before the text. We re-fetch live to avoid using a stale cached
        // image URL.
        if ($source === 'wc_grounded' && ! empty($products)) {
            $first = $products[0];
            if (! empty($first['id'])) {
                try {
                    $raw = $this->wc->get('products/'.$first['id']);
                    if (is_array($raw) && ! empty($raw['images'][0]['src'])) {
                        $imageUrl = (string) $raw['images'][0]['src'];
                        $product = ['id' => $first['id'], 'name' => $first['name'], 'image' => $imageUrl];
                    }
                } catch (Throwable $e) {
                    // image fetch failed — proceed with text only
                    $imageUrl = null;
                }
            }
        }

        // ============ Send image (transient-retry) ============
        if ($imageUrl && method_exists($driver, 'sendImage')) {
            $imgResult = $this->sendWithRetry(
                fn () => $driver->sendImage($conv->thread_id, $imageUrl),
                attempts: 2,
                label: 'image',
                conv: $conv,
            );
            $imageSent = $imgResult['ok'];
            // image failure is NOT fatal — continue to text
        }

        // ============ Typing simulation ============
        // Human typing pace; gives Meta time to deliver the image first.
        $typingSec = $this->computeTypingDelaySeconds($text);
        if (! app()->runningUnitTests() && $typingSec > 0) {
            sleep($typingSec);
        }

        // ============ Send text (transient-retry) ============
        $textResult = $this->sendWithRetry(
            fn () => $driver->sendText($conv->thread_id, $text),
            attempts: 2,
            label: 'text',
            conv: $conv,
        );

        if (! $textResult['ok']) {
            $this->writeLog($conv, 'failed', [
                'source' => $source,
                'image_sent' => $imageSent,
                'classification' => $textResult['classification'],
                'attempts' => $textResult['attempts'],
                'error' => $textResult['error'],
            ]);
            // For transient failures throw — Laravel will retry the whole job.
            // For permanent we swallow (no retry).
            if ($textResult['classification'] === 'transient') {
                throw new \RuntimeException('messenger_transient: '.$textResult['error']);
            }
            return ['ok' => false, 'action' => 'send_failed', 'error' => $textResult['error'] ?? 'unknown'];
        }

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'customer_id' => $conv->customer_id,
            'platform_msg_id' => $textResult['platform_msg_id'] ?? null,
            'direction' => Message::DIRECTION_OUT,
            'kind' => 'text',
            'body' => $text,
            'media_json' => $product ? [['url' => $product['image'], 'sent' => $imageSent, 'product_id' => $product['id']]] : null,
            'is_ai' => true,
            'author_employee_id' => null,
            'confidence' => null,
            'intent' => $suggestion['intent'] ?? null,
            'sent_at' => now(),
        ]);
        $conv->update(['last_outbound_at' => now()]);

        $this->writeLog($conv, 'sent', [
            'source' => $source,
            'image_sent' => $imageSent,
            'product_id' => $product['id'] ?? null,
            'msg_id' => $msg->id,
            'typing_delay_s' => $typingSec,
            'product_source' => $source === 'wc_grounded' ? 'woocommerce' : null,
        ]);
        AuditLog::record('ai', 'auto_reply_sent', 'conversation', $conv->id, [
            'source' => $source,
            'msg_id' => $msg->id,
            'image_sent' => $imageSent,
            'intent' => $suggestion['intent'] ?? null,
            'query' => $suggestion['query'] ?? null,
            'product_ids' => array_map(fn ($p) => $p['id'] ?? null, $products),
        ]);

        return ['ok' => true, 'action' => 'sent', 'message_id' => $msg->id];
    }

    /**
     * Send with one retry on transient failure (DNS, timeout, 5xx).
     * Permanent failures (4xx specific codes) skip retry.
     *
     * @param callable $send returns SendResult
     * @return array{ok: bool, classification: string, attempts: int, error: ?string, platform_msg_id: ?string}
     */
    private function sendWithRetry(callable $send, int $attempts, string $label, Conversation $conv): array
    {
        $lastError = null;
        $classification = 'unknown';
        $platformMsgId = null;
        $actualAttempts = 0;

        for ($i = 1; $i <= $attempts; $i++) {
            $actualAttempts = $i;
            try {
                $result = $send();
                if ($result->ok) {
                    return [
                        'ok' => true,
                        'classification' => 'success',
                        'attempts' => $i,
                        'error' => null,
                        'platform_msg_id' => $result->platformMsgId,
                    ];
                }
                $lastError = $result->error;
                $classification = $this->classifyError($lastError, $result->raw);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                $classification = $this->classifyError($lastError);
            }

            if ($classification === 'permanent') {
                break; // fatal, no retry
            }
            if ($i < $attempts) {
                $sleep = $i;
                $this->writeLog($conv, 'retry', [
                    'label' => $label,
                    'attempt' => $i,
                    'classification' => $classification,
                    'sleep_s' => $sleep,
                    'error' => $lastError,
                ]);
                if (! app()->runningUnitTests()) sleep($sleep);
            }
        }

        return [
            'ok' => false,
            'classification' => $classification,
            'attempts' => $actualAttempts,
            'error' => $lastError,
            'platform_msg_id' => $platformMsgId,
        ];
    }

    public function skip(Conversation $conv, string $reason, array $context = []): array
    {
        $this->writeLog($conv, 'skipped', array_merge(['reason' => $reason], $context));
        AuditLog::record('ai', 'auto_reply_skipped', 'conversation', $conv->id, [
            'reason' => $reason,
        ]);
        return ['ok' => false, 'action' => 'skipped', 'error' => $reason];
    }

    public function scheduledLog(Conversation $conv, int $delaySeconds, string $jobToken): void
    {
        $this->writeLog($conv, 'scheduled', [
            'delay_s' => $delaySeconds,
            'job_token' => substr($jobToken, 0, 8),
        ]);
    }

    private function writeLog(Conversation $conv, string $action, array $context): void
    {
        $line = sprintf(
            "[%s] conv=%d platform=%s thread=%s action=%s %s\n",
            now()->toIso8601String(),
            $conv->id,
            $conv->platform,
            $conv->thread_id,
            $action,
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        @file_put_contents(storage_path(self::LOG_FILE), $line, FILE_APPEND | LOCK_EX);
    }
}
