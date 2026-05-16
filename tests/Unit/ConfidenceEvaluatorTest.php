<?php

namespace Tests\Unit;

use App\Services\AI\ConfidenceEvaluator;
use Tests\TestCase;

class ConfidenceEvaluatorTest extends TestCase
{
    public function test_parses_meta_tail_and_strips_it_from_the_reply(): void
    {
        $eval = new ConfidenceEvaluator();
        $raw = "გამარჯობა! 👋 ეს არის ჩემი პასუხი.\n<meta>{\"confidence\": 0.83, \"intent\": \"greeting\", \"next_action\": \"reply\"}</meta>";

        $out = $eval->parse($raw);

        $this->assertSame(0.83, $out['meta']['confidence']);
        $this->assertSame('greeting', $out['meta']['intent']);
        $this->assertStringNotContainsString('<meta>', $out['clean']);
        $this->assertStringContainsString('გამარჯობა', $out['clean']);
    }

    public function test_passes_floor_when_confidence_above_config(): void
    {
        config()->set('chatbot.ai.min_confidence', 0.6);
        $eval = new ConfidenceEvaluator();

        $this->assertTrue($eval->passesFloor(0.7));
        $this->assertFalse($eval->passesFloor(0.3));
        $this->assertTrue($eval->passesFloor(null)); // missing → allow
    }

    public function test_handles_missing_meta_tail_gracefully(): void
    {
        $eval = new ConfidenceEvaluator();
        $out = $eval->parse('ეს არის უბრალო პასუხი ყოველგვარი ტეგების გარეშე.');

        $this->assertNull($out['meta']['confidence']);
        $this->assertSame('ეს არის უბრალო პასუხი ყოველგვარი ტეგების გარეშე.', $out['clean']);
    }
}
