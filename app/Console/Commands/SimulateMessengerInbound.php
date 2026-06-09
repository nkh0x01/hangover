<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIncomingMessage;
use App\Services\Channels\ChannelManager;
use Illuminate\Console\Command;

/**
 * Insert a synthetic Messenger inbound event into the inbox by feeding a
 * minimal Messenger payload through the real parser + ProcessIncomingMessage
 * job. Proves the parser → DB chain works independently of Meta actually
 * sending events.
 *
 * Usage:
 *   php artisan messenger:simulate-inbound
 *   php artisan messenger:simulate-inbound --sender=555111222 --text="გამარჯობა"
 */
class SimulateMessengerInbound extends Command
{
    protected $signature = 'messenger:simulate-inbound
        {--sender=555000111 : Synthetic sender PSID}
        {--text=ტესტ-მესიჯი სიმულატორიდან : Inbound text}
        {--name=Test User : Synthetic sender display name}';

    protected $description = 'Inject a fake Messenger inbound message into the inbox (proves parser → DB chain)';

    public function handle(ChannelManager $channels): int
    {
        $senderId = (string) $this->option('sender');
        $text = (string) $this->option('text');
        $name = (string) $this->option('name');
        $mid = 'mid.sim.'.time().'.'.bin2hex(random_bytes(4));

        $payload = [
            'object' => 'page',
            'entry' => [[
                'id' => '999999999',
                'time' => (int) (microtime(true) * 1000),
                'messaging' => [[
                    'sender' => ['id' => $senderId],
                    'recipient' => ['id' => '999999999'],
                    'timestamp' => (int) (microtime(true) * 1000),
                    'message' => [
                        'mid' => $mid,
                        'text' => $text,
                    ],
                ]],
            ]],
        ];

        $this->line('Payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        try {
            $driver = $channels->driver('messenger');
            $events = $driver->parseInbound($payload);
        } catch (\Throwable $e) {
            $this->error('parser threw: '.$e->getMessage());
            return 1;
        }

        if (empty($events)) {
            $this->error('parser returned 0 events — check parseInbound logic in MessengerDriver');
            return 1;
        }

        $this->info('parsed '.count($events).' event(s)');
        foreach ($events as $i => $event) {
            $eventArr = $event->toArray();
            $eventArr['sender_name'] = $name; // override for nicer inbox display

            $this->line(sprintf('  [%d] platform=%s sender=%s thread=%s kind=%s text=%s',
                $i, $event->platform, $event->senderId, $event->threadId, $event->kind, $event->text ?? '(no text)'
            ));

            try {
                ProcessIncomingMessage::dispatchSync($eventArr);
                $this->info('  ↳ ProcessIncomingMessage dispatched synchronously');
            } catch (\Throwable $e) {
                $this->error('  ↳ job failed: '.$e->getMessage());
                return 1;
            }
        }

        // Quick state summary
        $convoCount = \App\Models\Conversation::where('platform', 'messenger')->count();
        $msgCount = \App\Models\Message::whereHas('conversation', fn ($q) => $q->where('platform', 'messenger'))->count();
        $this->line("");
        $this->info("Inbox state: messenger conversations={$convoCount}, messages={$msgCount}");
        $this->line('Open /admin/inbox to verify.');

        return 0;
    }
}
