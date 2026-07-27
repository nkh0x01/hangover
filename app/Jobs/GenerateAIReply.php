<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiSuggestionService;
use App\Services\AI\AutoReplySender;
use App\Services\AI\ReplyEngine;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs after the debounce window. Aborts cheaply if a newer inbound has
 * already rotated the conversation's pending_reply_job_id (= stale).
 *
 * SAFETY GATES (each can independently veto):
 *   - master AUTO_REPLY_ENABLED off
 *   - per-channel toggle off
 *   - business hours gate active and outside hours
 *   - conversation has assigned_employee_id (human took it)
 *   - conversation.ai_paused (manual takeover)
 *   - conversation.escalated
 *   - customer.is_spam / is_blocked
 *   - per-conversation rate limit (max AI replies per hour)
 *   - AI returned no usable suggestion (validator_rejected, no_products_fallback w/o products)
 */
class GenerateAIReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(public int $conversationId, public string $jobToken) {}

    public function handle(
        AiSuggestionService $ai,
        AutoReplySender $sender,
        SettingsService $settings,
    ): void {
        $conv = Conversation::with('customer')->find($this->conversationId);
        if (! $conv) {
            return;
        }

        // Stale-token check: a newer inbound rotated the token → silently drop.
        if ($conv->pending_reply_job_id !== $this->jobToken) {
            return;
        }

        // ============ Safety gates ============
        if ($skip = $this->checkGates($conv, $settings)) {
            $sender->skip($conv, $skip);
            return;
        }

        // ============ Autonomous sales engine (full_auto) ============
        // When ROLLOUT_MODE=full_auto, hand off to the tool-use ReplyEngine
        // (product search, native cards, draft orders, BOG payment links,
        // tool-driven escalation). It sends its own replies with pacing.
        // All the safety gates above still apply. Opt-in: default is
        // public_product_only, so this branch is inert until explicitly enabled.
        if ($settings->rolloutMode() === 'full_auto') {
            try {
                app(ReplyEngine::class)->reply($conv);
                AuditLog::record('ai', 'reply.engine', 'conversation', $conv->id, [
                    'token' => substr($this->jobToken, 0, 8),
                    'mode' => 'full_auto',
                    'platform' => $conv->platform,
                ]);
            } catch (Throwable $e) {
                report($e);
                $sender->skip($conv, 'engine_exception: '.$e->getMessage());
            }

            return;
        }

        try {
            $suggestion = $ai->suggest($conv);
        } catch (Throwable $e) {
            report($e);
            $sender->skip($conv, 'ai_exception: '.$e->getMessage());
            return;
        }

        if (! ($suggestion['ok'] ?? false)) {
            $sender->skip($conv, 'ai_error: '.($suggestion['error'] ?? 'unknown'), $suggestion);
            return;
        }

        $source = $suggestion['source'] ?? '';
        $intent = $suggestion['intent'] ?? null;

        // For sensitive intents we DO NOT auto-reply — a human handles.
        if (in_array($intent, ['complaint', 'order_status'], true)) {
            $sender->skip($conv, 'sensitive_intent:'.$intent, $suggestion);
            return;
        }

        // WC unavailable (auth/network/blocked) — must NOT auto-reply and
        // must log specifically why. Admin needs to see this in inbox.
        if ($source === 'wc_unavailable') {
            $sender->skip($conv, 'woocommerce_unavailable: '.($suggestion['error'] ?? '?'), $suggestion);
            return;
        }

        // For product queries with no products found, we hold off on auto-
        // replying — the fallback line gets sent only manually by an agent
        // to avoid silencing a real product question with a non-answer.
        // Create a rich internal note so the human team sees the unanswered
        // query AND the diagnostic context (what we tried, what WC returned).
        if ($source === 'no_products_fallback') {
            $query = $suggestion['query'] ?? '?';
            $variants = $suggestion['queries_tried'] ?? [];
            $customerMessage = $this->latestCustomerMessage($conv);
            $variantsLine = $variants ? implode(', ', $variants) : '—';

            $noteBody = "🤖 AI auto-reply ჩერდება — WC-ში ვერ ვიპოვე პროდუქტი.\n"
                . "კლიენტმა მითხრა: \"{$customerMessage}\"\n"
                . "AI-მ ამოიგო query: \"{$query}\"\n"
                . "WooCommerce-ში გადავცადე: ".$variantsLine."\n"
                . "შედეგი: 0 პროდუქტი\n"
                . "შემდეგი ნაბიჯი: დაუკავშირდი კლიენტს ხელით, ან შეამოწმე ცარგი keyword/synonym mapping (KeywordMapper.php).";

            try {
                \App\Models\Note::create([
                    'conversation_id' => $conv->id,
                    'employee_id' => null,
                    'body' => $noteBody,
                    'pinned' => true,
                ]);
            } catch (\Throwable $e) {}

            // Enrich skip payload so /admin/inbox + audit log show the same
            // diagnostic info the note has.
            $sender->skip($conv, 'no_wc_products', [
                'source' => $source,
                'intent' => $intent,
                'query' => $query,
                'queries_tried' => $variants,
                'product_count' => 0,
                'customer_message' => $customerMessage,
            ]);
            return;
        }

        if ($source === 'validator_rejected') {
            $sender->skip($conv, 'validator_rejected', $suggestion);
            return;
        }

        // ===== Public rollout-mode gate =====
        // At this point $source is 'wc_grounded' or 'general'.
        $mode = $settings->rolloutMode();

        // public_receive_only: NEVER auto-send anything — just receive + note.
        if ($mode === 'public_receive_only') {
            $this->noteAwaitingHuman($conv, $suggestion, 'public_receive_only mode — auto-reply გათიშულია, ყველა მესიჯი ადამიანს გადაეცემა');
            $sender->skip($conv, 'rollout_receive_only', $suggestion);
            return;
        }

        // public_product_only (DEFAULT): auto-send ONLY clear, WC-grounded
        // product replies. Greetings / general questions / anything else →
        // pinned note + awaiting human, no auto-send.
        if ($mode === 'public_product_only' && $source !== 'wc_grounded') {
            $this->noteAwaitingHuman($conv, $suggestion,
                "public_product_only mode — non-product reply (source={$source}, intent={$intent}) ადამიანს გადაეცემა");
            $sender->skip($conv, 'rollout_product_only_non_product', $suggestion);
            return;
        }

        // OK to send (beta = both; public_product_only = wc_grounded only).
        $sender->send($conv, $suggestion);

        AuditLog::record('ai', 'reply.sent', 'conversation', $conv->id, [
            'token' => substr($this->jobToken, 0, 8),
            'source' => $source,
            'intent' => $intent,
            'rollout_mode' => $mode,
        ]);
    }

    /**
     * Most recent inbound (customer) message body for this conversation.
     * Used to enrich internal notes with what the customer actually said.
     */
    private function latestCustomerMessage(Conversation $conv): string
    {
        $msg = Message::where('conversation_id', $conv->id)
            ->where('direction', Message::DIRECTION_IN)
            ->latest('id')
            ->first();
        return trim((string) ($msg->body ?? ''));
    }

    /**
     * Create a pinned internal note marking the conversation as awaiting a
     * human reply (used when rollout mode suppresses auto-send).
     */
    private function noteAwaitingHuman(Conversation $conv, array $suggestion, string $why): void
    {
        $customerMessage = $this->latestCustomerMessage($conv);
        $draft = trim((string) ($suggestion['suggestion'] ?? ''));
        $body = "🤖→👤 AI auto-reply შეჩერდა — ❗ awaiting human.\n"
            . "მიზეზი: {$why}\n"
            . "კლიენტმა მითხრა: \"{$customerMessage}\"\n"
            . ($draft !== '' ? "AI-ის სავარაუდო პასუხი (NOT გაგზავნილი, შეგიძლია გამოიყენო): \"{$draft}\"\n" : '')
            . "ნაბიჯი: გადახედე და ხელით უპასუხე inbox-დან.";
        try {
            \App\Models\Note::create([
                'conversation_id' => $conv->id,
                'employee_id' => null,
                'body' => $body,
                'pinned' => true,
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Returns NULL if it's safe to auto-reply; otherwise returns a short
     * reason string for logging.
     */
    private function checkGates(Conversation $conv, SettingsService $settings): ?string
    {
        if (! $settings->isAutoReplyEnabledFor($conv->platform)) {
            return 'auto_reply_disabled_for_'.$conv->platform;
        }
        if (! $settings->isWithinBusinessHours()) {
            return 'outside_business_hours';
        }
        if ($conv->ai_paused) {
            return 'ai_paused_manual_takeover';
        }
        if ($conv->escalated) {
            return 'escalated';
        }
        if ($conv->assigned_employee_id) {
            return 'assigned_to_employee_'.$conv->assigned_employee_id;
        }
        if ($conv->customer && ($conv->customer->is_spam || $conv->customer->is_blocked)) {
            return 'customer_blocked_or_spam';
        }
        // Per-conversation hourly rate limit
        $max = $settings->getInt('AUTO_REPLY_MAX_PER_HOUR', 10);
        $countLastHour = Message::where('conversation_id', $conv->id)
            ->where('direction', Message::DIRECTION_OUT)
            ->where('is_ai', true)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($countLastHour >= $max) {
            return 'rate_limit_'.$countLastHour.'/'.$max;
        }
        return null;
    }

    public function failed(?Throwable $e = null): void
    {
        AuditLog::record('ai', 'reply.dead_letter', 'conversation', $this->conversationId, [
            'error' => $e?->getMessage(),
        ]);
    }
}
