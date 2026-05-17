<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Escalation\EscalationDispatcher;
use App\Services\Memory\CustomerMemory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The conductor. Given a conversation that just received a new
 * (debounced) inbound message, produce and send a reply.
 *
 * Flow:
 *   1. ToneAdapter picks a preset
 *   2. IntentDetector gets a coarse intent (cheap)
 *   3. Hard-escalation intents short-circuit to EscalationDispatcher
 *   4. Otherwise compose Claude messages with tools and loop until the
 *      model returns end_turn (executing tool_use blocks each round)
 *   5. Confidence gate
 *   6. Send via ChannelManager with natural pacing
 *   7. CustomerMemory.extract() in a follow-up job
 */
class ReplyEngine
{
    public function __construct(
        private ClaudeClient $claude,
        private PromptBuilder $prompts,
        private ToolRegistry $tools,
        private IntentDetector $intent,
        private ToneAdapter $tone,
        private ConfidenceEvaluator $confidence,
        private ChannelManager $channels,
        private EscalationDispatcher $escalation,
        private CustomerMemory $memory,
    ) {}

    public function reply(Conversation $conversation): void
    {
        if (! $conversation->isAIEnabled()) {
            return;
        }

        $customer = $conversation->customer;
        $lastInbound = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', Message::DIRECTION_IN)
            ->latest('id')
            ->first();

        if (! $lastInbound) {
            return;
        }

        // 1. Hard-stop intents → escalate immediately, no AI reply.
        $intent = $this->intent->detect($lastInbound->body ?? '');
        $hardStop = ['complaint', 'refund', 'warranty', 'manager_request'];
        if (in_array($intent, $hardStop, true)) {
            $this->escalation->dispatch(
                conversation: $conversation,
                customer: $customer,
                reason: "intent_$intent",
                urgency: $intent === 'manager_request' ? 'high' : 'medium',
                summary: mb_substr($lastInbound->body ?? '', 0, 280),
            );

            return;
        }

        $tonePreset = $this->tone->detect($conversation);
        $systemBlocks = $this->prompts->systemBlocks($customer, $conversation, $tonePreset);
        $messages = $this->prompts->historyMessages($conversation, (int) config('chatbot.ai.history_turns', 12));

        // 2. Run the tool-use loop.
        $replyText = $this->runToolLoop($customer, $conversation, $systemBlocks, $messages);
        if ($replyText === null) {
            // Loop bailed (tool failure / model refusal). Already escalated.
            return;
        }

        // 3. Confidence gate.
        $parsed = $this->confidence->parse($replyText);
        $meta = $parsed['meta'];
        $clean = $parsed['clean'];

        if (! $this->confidence->passesFloor($meta['confidence'] ?? null)) {
            $this->escalation->dispatch(
                conversation: $conversation,
                customer: $customer,
                reason: 'low_confidence',
                urgency: 'low',
                summary: 'AI confidence ' . ($meta['confidence'] ?? 'n/a') . ". Suggested reply was:\n$clean",
            );

            return;
        }

        // 4. Send with natural pacing.
        $this->sendPaced($conversation, $customer, $clean, $meta);

        // 5. Update memory (async would be nicer, but small inline pass is fine).
        try {
            $this->memory->extractFromTurn($customer, $lastInbound->body ?? '', $clean);
        } catch (Throwable $e) {
            Log::warning('memory.extract.failed', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Core tool-use loop. Returns the final assistant text, or null if
     * the model chose to escalate via tool (in which case the
     * EscalationDispatcher has already fired).
     */
    private function runToolLoop(Customer $customer, Conversation $conversation, array $systemBlocks, array $messages): ?string
    {
        $tools = $this->tools->definitions();
        $maxRounds = 5;
        $escalatedViaTool = false;

        for ($round = 0; $round < $maxRounds; $round++) {
            $resp = $this->claude->messages([
                'system' => $systemBlocks,
                'messages' => $messages,
                'tools' => $tools,
                'max_tokens' => 1024,
            ]);

            $stopReason = $resp['stop_reason'] ?? null;
            $content = $resp['content'] ?? [];

            // Append assistant turn so subsequent tool_results align.
            $messages[] = ['role' => 'assistant', 'content' => $content];

            if ($stopReason !== 'tool_use') {
                return $this->claude->extractText($resp);
            }

            $toolResultBlocks = [];
            foreach ($content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                $name = $block['name'] ?? '';
                $input = $block['input'] ?? [];
                $callId = $block['id'] ?? '';

                try {
                    $result = $this->tools->execute($name, $input, $customer, $conversation);
                } catch (Throwable $e) {
                    Log::error('tool.exception', ['name' => $name, 'msg' => $e->getMessage()]);
                    $result = ['error' => 'tool_failed', 'detail' => $e->getMessage()];
                }

                if ($name === 'escalate_to_human' && ! empty($result['escalated'])) {
                    $escalatedViaTool = true;
                }

                $toolResultBlocks[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $callId,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            if ($escalatedViaTool) {
                return null;
            }

            $messages[] = ['role' => 'user', 'content' => $toolResultBlocks];
        }

        // Loop got stuck — escalate.
        $this->escalation->dispatch(
            conversation: $conversation,
            customer: $customer,
            reason: 'tool_loop_overflow',
            urgency: 'low',
            summary: 'AI got stuck in a tool-use loop. Manual review needed.',
        );

        return null;
    }

    /**
     * Send the reply naturally — possibly split into chunks, with
     * typing indicator + per-chunk pacing.
     */
    private function sendPaced(Conversation $conversation, Customer $customer, string $text, array $meta): void
    {
        $driver = $this->channels->driver($conversation->platform);
        $chunks = $this->chunkReply($text);

        $cfg = config('chatbot.typing');

        foreach ($chunks as $i => $chunk) {
            $driver->setTyping($conversation->thread_id, true);

            $delayMs = max(
                $cfg['min_ms'],
                min($cfg['max_ms'], $cfg['min_ms'] + mb_strlen($chunk) * $cfg['per_char_ms'])
            );
            usleep($delayMs * 1000);

            $result = $driver->sendText($conversation->thread_id, $chunk);
            $driver->setTyping($conversation->thread_id, false);

            Message::create([
                'conversation_id' => $conversation->id,
                'customer_id' => $customer->id,
                'platform_msg_id' => $result->platformMsgId,
                'direction' => Message::DIRECTION_OUT,
                'kind' => 'text',
                'body' => $chunk,
                'is_ai' => true,
                'confidence' => $meta['confidence'] ?? null,
                'intent' => $meta['intent'] ?? null,
                'sent_at' => now(),
            ]);

            // Small natural pause between chunks.
            if ($i < count($chunks) - 1) {
                usleep(400_000);
            }
        }

        $conversation->update(['last_outbound_at' => now()]);
    }

    /**
     * Naive but effective chunker: split on blank lines, keep paragraphs
     * intact, max 2 chunks unless the model deliberately wrote 3+.
     */
    private function chunkReply(string $text): array
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [trim($text)];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), 'strlen'));

        if (count($paragraphs) === 1) {
            return $paragraphs;
        }
        if (count($paragraphs) > 3) {
            // Merge tail into the third chunk.
            $head = array_slice($paragraphs, 0, 2);
            $tail = implode("\n\n", array_slice($paragraphs, 2));

            return array_merge($head, [$tail]);
        }

        return $paragraphs;
    }

    /** Public helper used by jobs that want to push specific media. */
    public function sendProductCard(Conversation $conversation, Customer $customer, string $imageUrl, string $caption): void
    {
        $driver = $this->channels->driver($conversation->platform);
        $driver->sendMedia($conversation->thread_id, new MediaPayload('image', $imageUrl, $caption));

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'direction' => Message::DIRECTION_OUT,
            'kind' => 'image',
            'body' => $caption,
            'media_json' => ['url' => $imageUrl],
            'is_ai' => true,
            'sent_at' => now(),
        ]);
    }
}
