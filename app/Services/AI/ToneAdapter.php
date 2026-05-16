<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Picks a tone preset based on the customer's recent style. No ML —
 * cheap heuristics that work well enough on real chat data.
 */
class ToneAdapter
{
    public function detect(Conversation $conversation): string
    {
        $recent = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', Message::DIRECTION_IN)
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('body')
            ->filter()
            ->values()
            ->all();

        if (empty($recent)) {
            return 'friendly_warm';
        }

        $avgLen   = array_sum(array_map('mb_strlen', $recent)) / count($recent);
        $emojis   = $this->emojiCount(implode(' ', $recent));
        $allCaps  = $this->looksAngry(implode(' ', $recent));
        $hasQs    = str_contains(implode(' ', $recent), '?') || str_contains(implode(' ', $recent), '?');
        $sales    = $this->salesSignals(implode(' ', $recent));

        return match (true) {
            $allCaps          => 'friendly_warm',     // de-escalate
            $sales            => 'sales_focused',
            $avgLen <= 25     => 'short_punchy',
            $hasQs            => 'educational',
            $emojis >= 2      => 'friendly_warm',
            default           => 'friendly_warm',
        };
    }

    private function emojiCount(string $text): int
    {
        return preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text);
    }

    private function looksAngry(string $text): bool
    {
        if (preg_match_all('/[A-ZА-Я\x{10A0}-\x{10FF}]/u', $text) > 30) {
            return true;
        }
        $angryWords = ['რა?!', '!!!', 'უხეში', 'საშინელება', 'ვუჩივი', 'არ მუშაობს', 'მენეჯერი'];
        foreach ($angryWords as $w) {
            if (mb_stripos($text, $w) !== false) return true;
        }
        return false;
    }

    private function salesSignals(string $text): bool
    {
        $buy = ['ვიყიდი', 'მინდა', 'მაქვს ფული', 'როდის მოვა', 'მიყიდე', 'ვაიღო', 'ფასი', 'ფასდაკლება', 'შემიძლია', 'მინდა შევუკვეთო'];
        foreach ($buy as $w) {
            if (mb_stripos($text, $w) !== false) return true;
        }
        return false;
    }
}
