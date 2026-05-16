<?php

namespace App\Http\Middleware;

use App\Exceptions\WebhookVerificationException;
use App\Services\Channels\ChannelManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class VerifyMetaSignature
{
    public function __construct(private ChannelManager $channels) {}

    public function handle(Request $request, Closure $next)
    {
        // GET = verification handshake, no signature.
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $platform = $request->route('channel');
        if (! $platform) {
            return response('missing channel', 400);
        }

        try {
            $this->channels->driver($platform)->verifySignature($request);
        } catch (WebhookVerificationException $e) {
            return response($e->getMessage(), 403);
        } catch (Throwable $e) {
            report($e);
            return response('verification_error', 500);
        }

        return $next($request);
    }
}
