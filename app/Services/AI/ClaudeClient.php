<?php

namespace App\Services\AI;

use App\Services\SettingsService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin Anthropic Messages API client. Supports tool use and prompt
 * caching. Keep this dumb — the orchestration lives in ReplyEngine.
 *
 * SettingsService is consulted first for ANTHROPIC_API_KEY / model overrides
 * so admin-saved values take effect without an env-edit / redeploy.
 */
class ClaudeClient
{
    private Client $http;

    public function __construct(private SettingsService $settings)
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
        // Prefer admin-saved model (DB) over .env, with config() fallback
        $isLight = (bool) ($args['light'] ?? false);
        $modelKey = $isLight ? 'ANTHROPIC_MODEL_LIGHT' : 'ANTHROPIC_MODEL_PRIMARY';
        $defaultModel = config('ai.models.' . ($isLight ? 'light' : 'primary'));
        $model = $args['model'] ?? $this->settings->get($modelKey) ?: $defaultModel;

        $maxTokens = $args['max_tokens']
            ?? (int) ($this->settings->get('ANTHROPIC_MAX_TOKENS') ?: config('ai.limits.max_tokens', 1024));

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
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

        // DB first, .env fallback — admin-saved key wins.
        $apiKey = $this->settings->get('ANTHROPIC_API_KEY') ?: config('ai.anthropic.api_key');
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
            $err = $data['error'] ?? [];
            $type = (string) ($err['type'] ?? '');
            $msg = (string) ($err['message'] ?? "http_$status");
            // Surface a stable categorization so callers can produce
            // user-friendly Georgian messages without parsing strings.
            throw new RuntimeException("anthropic_{$type}: {$msg}");
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
