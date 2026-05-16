<?php

namespace App\Services\AI;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin Anthropic Messages API client. Supports tool use and prompt
 * caching. Keep this dumb — the orchestration lives in ReplyEngine.
 */
class ClaudeClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => rtrim(config('ai.anthropic.base_url'), '/') . '/',
            'timeout' => (int) config('ai.anthropic.timeout', 60),
            'http_errors' => false,
        ]);
    }

    /**
     * Send a Messages API request.
     *
     * @param array{
     *   model?: string,
     *   system?: array|string,
     *   messages: array,
     *   tools?: array,
     *   max_tokens?: int,
     *   temperature?: float,
     *   light?: bool
     * } $args
     */
    public function messages(array $args): array
    {
        $model = $args['model']
            ?? config('ai.models.' . (($args['light'] ?? false) ? 'light' : 'primary'));

        $body = [
            'model' => $model,
            'max_tokens' => $args['max_tokens'] ?? config('ai.limits.max_tokens', 1024),
            'messages' => $args['messages'],
        ];

        if (! empty($args['system'])) {
            $body['system'] = $args['system'];
        }
        if (! empty($args['tools'])) {
            $body['tools'] = $args['tools'];
        }
        if (isset($args['temperature'])) {
            $body['temperature'] = $args['temperature'];
        }

        $apiKey = config('ai.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => config('ai.anthropic.version'),
            'content-type' => 'application/json',
        ];
        if ($beta = config('ai.anthropic.beta')) {
            $headers['anthropic-beta'] = $beta;
        }

        $res = $this->http->post('v1/messages', [
            'headers' => $headers,
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);

        $status = $res->getStatusCode();
        $data = json_decode((string) $res->getBody(), true) ?: [];

        if ($status >= 400) {
            Log::error('anthropic.error', ['status' => $status, 'body' => $data, 'model' => $model]);
            throw new RuntimeException('Anthropic API error: ' . ($data['error']['message'] ?? "http_$status"));
        }

        return $data;
    }

    /**
     * Convenience: ask for a single string response (no tools).
     */
    public function complete(string $system, string $user, bool $light = true): string
    {
        $resp = $this->messages([
            'light' => $light,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
            'max_tokens' => 400,
        ]);

        return $this->extractText($resp);
    }

    public function extractText(array $resp): string
    {
        $out = '';
        foreach (($resp['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $out .= $block['text'];
            }
        }

        return trim($out);
    }
}
