<?php

namespace App\Http\Controllers\Webhooks;

use App\Jobs\ProcessIncomingComment;
use App\Jobs\ProcessIncomingMessage;
use App\Services\Channels\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One controller handles all three channels (whatsapp, messenger,
 * instagram). Differences live in the drivers, not here.
 */
class MetaWebhookController extends Controller
{
    public function __construct(private ChannelManager $channels) {}

    /** GET /webhooks/{channel} — verification handshake. */
    public function verify(Request $request, string $channel)
    {
        $challenge = $this->channels->driver($channel)->verifyWebhook($request);
        return $challenge !== null
            ? response($challenge, 200)
            : response('forbidden', 403);
    }

    /** POST /webhooks/{channel} — inbound events. */
    public function receive(Request $request, string $channel)
    {
        $driver = $this->channels->driver($channel);

        try {
            $events = $driver->parseInbound($request->json()->all() ?? []);
        } catch (Throwable $e) {
            report($e);
            // Always 200 to Meta — they retry aggressively on non-200.
            return response('ok', 200);
        }

        foreach ($events as $event) {
            if ($event->isComment()) {
                ProcessIncomingComment::dispatch($event->toArray())
                    ->onQueue('comments');
            } else {
                ProcessIncomingMessage::dispatch($event->toArray())
                    ->onQueue('inbound');
            }
        }

        if (count($events) === 0) {
            Log::debug('webhook.no_events', [
                'channel' => $channel,
                'payload' => $request->json()->all(),
            ]);
        }

        return response('ok', 200);
    }
}
