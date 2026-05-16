<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\AI\ToneAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToneAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_messages_get_short_punchy_tone(): void
    {
        $c = $this->convoWithMessages(['ok', 'ok', 'ფასი?']);
        $tone = (new ToneAdapter())->detect($c);
        $this->assertSame('short_punchy', $tone);
    }

    public function test_question_marks_pick_educational(): void
    {
        $c = $this->convoWithMessages([
            'რა განსხვავებაა iPhone 15-სა და 15 Pro-ს შორის ფოტოაპარატის მხრივ?',
        ]);
        $tone = (new ToneAdapter())->detect($c);
        $this->assertSame('educational', $tone);
    }

    public function test_buying_signals_pick_sales_focused(): void
    {
        $c = $this->convoWithMessages(['მინდა შევუკვეთო ეს ქეისი დღესვე ფასი მითხარით']);
        $tone = (new ToneAdapter())->detect($c);
        $this->assertSame('sales_focused', $tone);
    }

    private function convoWithMessages(array $bodies): Conversation
    {
        $cust = Customer::create([
            'platform' => 'whatsapp',
            'platform_user_id' => '995599'.random_int(1000, 9999),
        ]);
        $conv = Conversation::create([
            'customer_id' => $cust->id,
            'platform'    => 'whatsapp',
            'thread_id'   => $cust->platform_user_id,
        ]);
        foreach ($bodies as $b) {
            Message::create([
                'conversation_id' => $conv->id,
                'customer_id'     => $cust->id,
                'direction'       => Message::DIRECTION_IN,
                'kind'            => 'text',
                'body'            => $b,
            ]);
        }
        return $conv;
    }
}
