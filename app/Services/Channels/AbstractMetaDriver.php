<?php

namespace App\Services\Channels;

use App\Exceptions\WebhookVerificationException;
use App\Services\Channels\Contracts\ChannelDriver;
use App\Services\Channels\DTO\MediaPayload;
use App\Services\Channels\DTO\SendResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shared plumbing for Meta-backed channels: webhook verification,
 * signature checks, Graph API HTTP calls.
 *
 * Each concrete driver provides the channel-specific bits: which env
 * keys to read, how to parse inbound payloads, how to map outbound
 * sends to Graph endpoints.
 */
abstract class AbstractMetaDriver implements ChannelDriver
{
    protected array $config;

    protected Client $http;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->http = new Client([
            'base_uri' => rtrim($config['graph_base'] ?? 'https://graph.facebook.com', '/') . '/' . ($config['graph_version'] ?? 'v20.0') . '/',
            'timeout' => 15,
            'http_errors' => false,
        ]);
    }

    public function verifyWebhook(Request $r): ?string
    {
        $mode = $r->query('hub_mode');
        $token = $r->query('hub_verify_token');
        $challenge = $r->query('hub_challenge');

        if ($mode === 'subscribe' && hash_equals((string) $this->config['verify_token'], (string) $token)) {
            return (string) $challenge;
        }

        return null;
    }

    public function verifySignature(Request $r): void
    {
        $secret = $this->config['app_secret'] ?? null;
        if (! $secret) {
            // No secret configured → fail closed in production, allow in local.
            if (app()->environment('production')) {
                throw new WebhookVerificationException('no_app_secret_configured');
            }

            return;
        }

        $header = $r->header('X-Hub-Signature-256');
        if (! $header || ! str_starts_with($header, 'sha256=')) {
            throw new WebhookVerificationException('missing_signature_header');
        }

        $expected = substr($header, 7);
        $computed = hash_hmac('sha256', $r->getContent(), $secret);

        if (! hash_equals($expected, $computed)) {
            throw new WebhookVerificationException('signature_mismatch');
        }
    }

    /** Convenience: POST to Graph with the channel's access token. */
    protected function graphPost(string $path, array $json, ?string $tokenOverride = null): array
    {
        $token = $tokenOverride ?? ($this->config['access_token'] ?? $this->config['page_access_token'] ?? null);

        try {
            $res = $this->http->post(ltrim($path, '/'), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($json, JSON_UNESCAPED_UNICODE),
            ]);

            $status = $res->getStatusCode();
            $body = (string) $res->getBody();
            $data = json_decode($body, true) ?: ['raw' => $body];

            if ($status >= 400) {
                Log::warning('graph.error', ['platform' => $this->platform(), 'path' => $path, 'status' => $status, 'body' => $data]);
            }

            return ['status' => $status, 'data' => $data];
        } catch (GuzzleException $e) {
            Log::error('graph.exception', ['platform' => $this->platform(), 'path' => $path, 'msg' => $e->getMessage()]);

            return ['status' => 0, 'data' => ['error' => ['message' => $e->getMessage()]]];
        }
    }

    /** Convenience: GET from Graph with the channel's access token. */
    protected function graphGet(string $path, array $query = [], ?string $tokenOverride = null): array
    {
        $token = $tokenOverride ?? ($this->config['access_token'] ?? $this->config['page_access_token'] ?? null);
        if ($token) {
            $query['access_token'] = $token;
        }

        try {
            $res = $this->http->get(ltrim($path, '/'), [
                'query' => $query,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $status = $res->getStatusCode();
            $body = (string) $res->getBody();
            $data = json_decode($body, true) ?: ['raw' => $body];

            if ($status >= 400) {
                Log::warning('graph.get.error', ['platform' => $this->platform(), 'path' => $path, 'status' => $status, 'body' => $data]);
            }

            return ['status' => $status, 'data' => $data];
        } catch (GuzzleException $e) {
            Log::error('graph.get.exception', ['platform' => $this->platform(), 'path' => $path, 'msg' => $e->getMessage()]);
            return ['status' => 0, 'data' => ['error' => ['message' => $e->getMessage()]]];
        }
    }

    protected function asSendResult(array $resp): SendResult
    {
        $status = $resp['status'] ?? 0;
        $data = $resp['data'] ?? [];

        if ($status >= 200 && $status < 300) {
            $id = $data['messages'][0]['id']
                ?? $data['message_id']
                ?? $data['id']
                ?? null;

            return SendResult::ok($id, $data);
        }

        return SendResult::fail(
            $data['error']['message'] ?? "http_$status",
            $data,
        );
    }

    /** Default: typing/comments are no-op unless overridden. */
    public function setTyping(string $recipient, bool $on): void
    {
        // No-op by default.
    }

    public function replyToComment(string $commentId, string $text): SendResult
    {
        return SendResult::fail('not_supported');
    }

    public function privateReplyToComment(string $commentId, string $text): SendResult
    {
        return SendResult::fail('not_supported');
    }

    /**
     * Default: degrade gracefully — drivers without native button
     * support emit the text body plus an inline list of choices.
     */
    public function sendInteractiveButtons(
        string $recipient,
        string $bodyText,
        array $buttons,
        ?MediaPayload $header = null,
        ?string $footerText = null,
    ): SendResult {
        $lines = [$bodyText];
        foreach ($buttons as $i => $b) {
            $lines[] = ($i + 1) . ') ' . ($b['title'] ?? '');
        }
        if ($footerText) {
            $lines[] = '';
            $lines[] = $footerText;
        }

        return $this->sendText($recipient, implode("\n", $lines));
    }

    /** Default: not supported. WhatsApp overrides this. */
    public function sendTemplate(
        string $recipient,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): SendResult {
        return SendResult::fail('not_supported');
    }
}
