<?php

namespace Tests\Feature;

use App\Jobs\GenerateAIReply;
use App\Jobs\ProcessIncomingMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessIncomingMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_upserts_customer_conversation_and_schedules_debounced_reply(): void
    {
        Queue::fake();

        $event = [
            'platform' => 'whatsapp',
            'platform_msg_id' => 'wamid.test1',
            'thread_id' => '995599100100',
            'sender_id' => '995599100100',
            'sender_name' => 'Nika',
            'kind' => 'text',
            'text' => 'გამარჯობა, მაინტერესებს iPhone 15 ქეისი',
            'media' => [],
            'received_at' => time(),
            'raw' => [],
        ];

        (new ProcessIncomingMessage($event))->handle();

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);

        $conv = Conversation::first();
        $this->assertNotNull($conv->pending_reply_job_id);
        $this->assertNotNull($conv->last_inbound_at);

        Queue::assertPushed(GenerateAIReply::class, function (GenerateAIReply $job) use ($conv) {
            return $job->conversationId === $conv->id
                && $job->jobToken === $conv->pending_reply_job_id;
        });
    }

    public function test_second_message_rotates_token_so_stale_replies_are_cancelled(): void
    {
        Queue::fake();

        $event = [
            'platform' => 'whatsapp', 'thread_id' => 'x', 'sender_id' => 'x',
            'sender_name' => null, 'kind' => 'text', 'text' => 'ფასი?',
            'media' => [], 'received_at' => time(), 'raw' => [],
            'platform_msg_id' => 'm1',
        ];

        (new ProcessIncomingMessage($event))->handle();
        $tok1 = Conversation::first()->pending_reply_job_id;

        $event['platform_msg_id'] = 'm2';
        $event['text'] = 'ფასი iPhone 15 Pro-ზე?';
        (new ProcessIncomingMessage($event))->handle();
        $tok2 = Conversation::first()->fresh()->pending_reply_job_id;

        $this->assertNotSame($tok1, $tok2);
        $this->assertSame(2, Message::count());
    }

    public function test_idempotent_on_duplicate_platform_msg_id(): void
    {
        Queue::fake();

        $event = [
            'platform' => 'whatsapp', 'thread_id' => 'y', 'sender_id' => 'y',
            'sender_name' => null, 'kind' => 'text', 'text' => 'hi',
            'media' => [], 'received_at' => time(), 'raw' => [],
            'platform_msg_id' => 'same-id',
        ];

        (new ProcessIncomingMessage($event))->handle();
        (new ProcessIncomingMessage($event))->handle();

        $this->assertSame(1, Message::count());
    }
}
