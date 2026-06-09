<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;

/**
 * Quick diagnostic: parse the last N Messenger webhook events out of the
 * raw log and cross-reference each one with DB state. Helps answer:
 *   "did Meta send a POST for X?"
 *   "did the parser create a row?"
 *   "did auto-reply fire or skip?"
 *
 * Usage:
 *   php artisan messenger:recent-webhooks
 *   php artisan messenger:recent-webhooks --limit=50
 */
class MessengerRecentWebhooksCommand extends Command
{
    protected $signature = 'messenger:recent-webhooks {--limit=20}';
    protected $description = 'Show last N Messenger webhook events with sender, text, response, DB state';

    public function handle(): int
    {
        $path = storage_path('logs/messenger-webhook-raw.log');
        if (! is_file($path)) {
            $this->error("Log file not found: $path");
            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        // Each event block starts with a divider line
        $blocks = preg_split('/={40,}/', $contents);
        $events = [];

        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '' || ! str_contains($b, 'POST https://bot.gadget.ge/webhooks/messenger')) {
                continue;
            }

            $event = [
                'ts' => null,
                'ip' => null,
                'sender_psid' => null,
                'text' => null,
                'mid' => null,
                'page_id' => null,
                'response_code' => null,
                'response_body' => null,
            ];

            if (preg_match('/^\[([^\]]+)\] POST/m', $b, $m)) $event['ts'] = $m[1];
            if (preg_match('/^IP:\s*(\S+)/m', $b, $m)) $event['ip'] = $m[1];
            // sender id
            if (preg_match('/"sender":\s*\{\s*"id":\s*"([^"]+)"/', $b, $m)) $event['sender_psid'] = $m[1];
            // page id
            if (preg_match('/"id":\s*"([^"]+)"\s*,\s*"messaging"/', $b, $m)) $event['page_id'] = $m[1];
            // text
            if (preg_match('/"text":\s*"((?:[^"\\\\]|\\\\.)*)"/', $b, $m)) {
                $event['text'] = json_decode('"'.$m[1].'"');
            }
            // mid
            if (preg_match('/"mid":\s*"([^"]+)"/', $b, $m)) $event['mid'] = $m[1];
            // response
            if (preg_match('/response\s+(\d+)\s+/', $b, $m)) $event['response_code'] = (int) $m[1];
            if (preg_match('/body:\s*(.+?)$/', $b, $m)) $event['response_body'] = trim($m[1]);

            $events[] = $event;
        }

        $limit = (int) $this->option('limit');
        $events = array_slice($events, -$limit);

        if (empty($events)) {
            $this->warn('No POST events found in raw log.');
            return self::SUCCESS;
        }

        $this->line(sprintf('Found %d Messenger POST events (showing last %d)', count($events), $limit));
        $this->line('');

        $rows = [];
        foreach ($events as $e) {
            $convId = null;
            $msgId = null;
            $isAiReply = null;
            if ($e['sender_psid']) {
                $conv = Conversation::where('platform', 'messenger')
                    ->where('thread_id', $e['sender_psid'])
                    ->first();
                if ($conv) {
                    $convId = $conv->id;
                    if ($e['mid']) {
                        $msg = Message::where('platform_msg_id', $e['mid'])->first();
                        if ($msg) $msgId = $msg->id;
                    }
                    // Was there an outbound AI reply after this msg?
                    if ($e['ts']) {
                        try {
                            $eventTime = new \DateTime($e['ts']);
                            $isAiReply = (bool) Message::where('conversation_id', $conv->id)
                                ->where('direction', 'outbound')
                                ->where('is_ai', true)
                                ->where('created_at', '>=', $eventTime->format('Y-m-d H:i:s'))
                                ->where('created_at', '<=', $eventTime->modify('+5 minutes')->format('Y-m-d H:i:s'))
                                ->exists();
                        } catch (\Throwable $e2) {}
                    }
                }
            }

            $rows[] = [
                'time' => $e['ts'] ? substr($e['ts'], 11, 8) : '?',
                'psid' => $e['sender_psid'] ? (strlen($e['sender_psid']) > 14 ? substr($e['sender_psid'], 0, 14).'…' : $e['sender_psid']) : '?',
                'text' => $e['text'] ? mb_substr($e['text'], 0, 30) : '?',
                'http' => $e['response_code'] ?? '?',
                'body' => mb_substr($e['response_body'] ?? '', 0, 25),
                'conv' => $convId ?? '—',
                'msg' => $msgId ?? '—',
                'ai_reply' => $isAiReply === true ? '✓' : ($isAiReply === false ? '—' : '?'),
            ];
        }

        $this->table(
            ['time', 'sender psid', 'text', 'http', 'response', 'conv#', 'msg#', 'ai_reply'],
            $rows,
        );

        // Distinct senders
        $psids = collect($events)->pluck('sender_psid')->filter()->unique()->values();
        $this->line('');
        $this->line('Distinct sender PSIDs: '.$psids->count());
        foreach ($psids as $p) $this->line('  · '.$p);

        if ($psids->count() === 1) {
            $this->line('');
            $this->warn('⚠ Only ONE PSID has ever sent a Messenger POST.');
            $this->warn('  If this is the page admin/owner only, your Meta app is likely in');
            $this->warn('  Development Mode — Meta blocks DMs from users who are not added');
            $this->warn('  as App Roles (admin/developer/tester).');
            $this->line('');
            $this->info('  Fix: Meta App Dashboard → App Roles → Roles → Add admin/developer/tester');
            $this->info('       OR submit the app for review and switch to Live mode.');
        }

        return self::SUCCESS;
    }
}
