<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs the raw HTTP traffic for /webhooks/messenger to
 * storage/logs/messenger-webhook-raw.log BEFORE signature verification runs,
 * so we can diagnose Meta-side issues (whether Meta sends, signature
 * problems, payload shape) independently of the rest of the pipeline.
 *
 * Only Messenger traffic is logged — WhatsApp / Instagram requests pass
 * through untouched.
 */
class LogMessengerWebhook
{
    private const LOG_FILE = 'logs/messenger-webhook-raw.log';

    public function handle(Request $request, Closure $next)
    {
        if ($request->route('channel') !== 'messenger') {
            return $next($request);
        }

        $this->writeLog($this->formatInbound($request));

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->writeLog(
                "  ↳ EXCEPTION before response: ".get_class($e).' · '.$e->getMessage()."\n"
            );
            throw $e;
        }

        $this->writeLog($this->formatOutbound($response));

        return $response;
    }

    private function formatInbound(Request $r): string
    {
        $headers = [];
        foreach ($r->headers->all() as $k => $values) {
            $headers[$k] = $this->isSensitiveHeader($k)
                ? array_map(fn ($v) => $this->mask((string) $v), $values)
                : $values;
        }

        $body = $r->getContent();
        if (strlen($body) > 20000) {
            $body = substr($body, 0, 20000)."\n...(truncated to 20kb)";
        }

        return sprintf(
            "\n========================================\n[%s] %s %s\nIP: %s\nUser-Agent: %s\nHeaders:\n%s\nQuery: %s\nBody (%d bytes):\n%s\n",
            now()->toIso8601String(),
            $r->method(),
            $r->fullUrl(),
            $r->ip(),
            $r->userAgent() ?? '-',
            json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            json_encode($r->query(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            strlen($r->getContent()),
            $body !== '' ? $body : '(empty)',
        );
    }

    private function formatOutbound(Response $response): string
    {
        $content = $response->getContent();
        if ($content !== false && strlen($content) > 500) {
            $content = substr($content, 0, 500)."\n...(truncated)";
        }
        return sprintf(
            "  ↳ response %d %s\n  ↳ body: %s\n",
            $response->getStatusCode(),
            $response->headers->get('content-type', '?'),
            $content === false ? '(non-string)' : $content,
        );
    }

    private function isSensitiveHeader(string $name): bool
    {
        $lower = strtolower($name);
        return in_array($lower, [
            'x-hub-signature',
            'x-hub-signature-256',
            'authorization',
            'cookie',
        ], true);
    }

    private function mask(string $val): string
    {
        $len = strlen($val);
        if ($len <= 16) {
            return str_repeat('•', $len);
        }
        return substr($val, 0, 12).'…'.substr($val, -4);
    }

    private function writeLog(string $msg): void
    {
        $path = storage_path(self::LOG_FILE);
        @file_put_contents($path, $msg, FILE_APPEND | LOCK_EX);
    }
}
