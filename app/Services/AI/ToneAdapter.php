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

        $avgLen = array_sum(array_map('mb_strlen', $recent)) / count($recent);
        $emojis = $this->emojiCount(implode(' ', $recent));
        $allCaps = $this->looksAngry(implode(' ', $recent));
        $hasQs = str_contains(implode(' ', $recent), '?') || str_contains(implode(' ', $recent), '?');
        $sales = $this->salesSignals(implode(' ', $recent));

        // Order matters: short style wins over sales-signal so a
        // "ფასი?" stays brief instead of triggering a sales pitch.
        return match (true) {
            $allCaps => 'friendly_warm',     // de-escalate
            $avgLen <= 25 => 'short_punchy',
            $sales => 'sales_focused',
            $hasQs => 'educational',
            $emojis >= 2 => 'friendly_warm',
            default => 'friendly_warm',
        };
    }

    private function emojiCount(string $text): int
    {
        return preg_match_all('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text);
    }

    private function looksAngry(string $text): bool
    {
        // ALL-CAPS check: only Latin + Cyrillic. Georgian script has no
        // upper/lower distinction in normal use, so its presence must
        // not count as shouting.
        if (preg_match_all('/[A-ZА-Я]/u', $text) > 30) {
            return true;
        }
        $angryWords = ['რა?!', '!!!', 'უხეში', 'საშინელება', 'ვუჩივი', 'არ მუშაობს', 'მენეჯერი'];
        foreach ($angryWords as $w) {
            if (mb_stripos($text, $w) !== false) {
                return true;
            }
        }

        return false;
    }

    private function salesSignals(string $text): bool
    {
        $buy = ['ვიყიდი', 'მინდა', 'მაქვს ფული', 'როდის მოვა', 'მიყიდე', 'ვაიღო', 'ფასი', 'ფასდაკლება', 'შემიძლია', 'მინდა შევუკვეთო'];
        foreach ($buy as $w) {
            if (mb_stripos($text, $w) !== false) {
                return true;
            }
        }

        return false;
    }
}
