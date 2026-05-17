<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Verifies the X-WC-Webhook-Signature header (HMAC-SHA256, base64-encoded)
 * against the raw body using the configured webhook secret.
 */
class VerifyWooSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('gadget.webhook_secret');
        if (! $secret) {
            if (app()->environment('production')) {
                return response('webhook_secret_missing', 500);
            }

            return $next($request); // permissive in local/dev
        }

        $header = $request->header('X-WC-Webhook-Signature');
        if (! $header) {
            return response('missing_signature', 403);
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
        if (! hash_equals($expected, $header)) {
            return response('signature_mismatch', 403);
        }

        return $next($request);
    }
}
