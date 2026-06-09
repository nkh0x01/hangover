<?php

namespace App\Console\Commands;

use App\Services\AI\ClaudeClient;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnostic for the AI integration. Prints exactly which source the key
 * is being read from (DB or .env or not_set), masked snippet, current
 * model selections, and runs a 1-token Anthropic round-trip to confirm
 * the key actually works.
 *
 * NEVER prints the raw key. Designed to be safe to paste into a ticket.
 *
 * Usage:
 *   php artisan ai:check-config
 *   php artisan ai:check-config --no-call   # skip the API round-trip
 */
class AiCheckConfigCommand extends Command
{
    protected $signature = 'ai:check-config {--no-call : skip the Anthropic round-trip}';
    protected $description = 'Inspect ANTHROPIC_API_KEY source + verify reachability';

    public function handle(SettingsService $settings, ClaudeClient $claude): int
    {
        $this->line('================ AI configuration check ================');

        $dbVal = null;
        $envVal = null;
        $configVal = null;
        try {
            $dbVal = \App\Models\Setting::where('key', 'ANTHROPIC_API_KEY')->first()?->value;
        } catch (Throwable $e) {
            $this->error('DB read failed: '.$e->getMessage());
        }
        $envVal = env('ANTHROPIC_API_KEY') ?: null;
        $configVal = config('ai.anthropic.api_key') ?: null;
        $svcVal = $settings->get('ANTHROPIC_API_KEY');

        $source = match (true) {
            !empty($dbVal) => 'db',
            !empty($envVal) => 'env',
            !empty($configVal) => 'config_fallback',
            default => 'not_set',
        };

        $this->line('  ANTHROPIC_API_KEY:');
        $this->line('    source                : '.$source);
        $this->line('    DB row exists         : '.($dbVal ? 'yes (len='.strlen($dbVal).')' : 'no'));
        $this->line('    .env raw              : '.($envVal ? 'yes (len='.strlen($envVal).')' : 'no'));
        $this->line('    config(ai...)         : '.($configVal ? 'yes (len='.strlen($configVal).')' : 'no'));
        $this->line('    SettingsService::get  : '.($svcVal ? 'yes (len='.strlen($svcVal).')' : 'NO ← bug if DB has row'));
        $this->line('    masked                : '.$this->mask($svcVal));

        $this->line('');
        $this->line('  Model selection:');
        $primary = $settings->get('ANTHROPIC_MODEL_PRIMARY') ?: config('ai.models.primary');
        $light = $settings->get('ANTHROPIC_MODEL_LIGHT') ?: config('ai.models.light');
        $maxTokens = $settings->get('ANTHROPIC_MAX_TOKENS') ?: config('ai.limits.max_tokens');
        $this->line("    primary               : {$primary}");
        $this->line("    light                 : {$light}");
        $this->line("    max_tokens            : {$maxTokens}");

        if ($this->option('no-call')) {
            $this->line('');
            $this->info('--no-call set; skipping API round-trip.');
            return self::SUCCESS;
        }

        if (! $svcVal) {
            $this->line('');
            $this->error('Cannot make API call — no key resolvable. Save it in /admin/integrations → AI.');
            return self::FAILURE;
        }

        $this->line('');
        $this->line('  Round-trip test (light model, 8 tokens):');
        try {
            $resp = $claude->messages([
                'light' => true,
                'system' => 'Reply with just "PONG"',
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 8,
            ]);
            $text = trim($claude->extractText($resp));
            $usage = $resp['usage'] ?? [];
            $this->info('    ✓ API works');
            $this->line('    model used            : '.($resp['model'] ?? '?'));
            $this->line('    response              : '.$text);
            $this->line('    input tokens          : '.($usage['input_tokens'] ?? '?'));
            $this->line('    output tokens         : '.($usage['output_tokens'] ?? '?'));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('    ✗ API call failed');
            $this->line('    error: '.$e->getMessage());
            $this->line('');
            $this->line('  Likely cause: '.$this->guessCause($e->getMessage()));
            return self::FAILURE;
        }
    }

    private function mask(?string $v): string
    {
        if (! $v) return '(empty)';
        $len = strlen($v);
        if ($len <= 12) return str_repeat('•', $len);
        return substr($v, 0, 7).str_repeat('•', max(0, $len - 11)).substr($v, -4);
    }

    private function guessCause(string $msg): string
    {
        $m = strtolower($msg);
        if (str_contains($m, 'invalid x-api-key') || str_contains($m, 'invalid_api_key') || str_contains($m, 'authentication')) {
            return 'invalid_key — key is wrong or revoked, regenerate in Anthropic console';
        }
        if (str_contains($m, 'credit') || str_contains($m, 'balance') || str_contains($m, 'insufficient')) {
            return 'insufficient_credits — top up at console.anthropic.com → Plans & Billing';
        }
        if (str_contains($m, 'rate') || str_contains($m, '429') || str_contains($m, 'too many')) {
            return 'rate_limit — too many requests; wait or upgrade tier';
        }
        if (str_contains($m, 'model') && (str_contains($m, 'not found') || str_contains($m, 'unavailable') || str_contains($m, 'does not exist'))) {
            return 'model_unavailable — model id wrong/retired; check /admin/integrations → AI';
        }
        if (str_contains($m, 'permission') || str_contains($m, 'forbidden')) {
            return 'permission_denied — API key lacks scope; create a new key with messages write';
        }
        if (str_contains($m, 'connect') || str_contains($m, 'timeout') || str_contains($m, 'resolve')) {
            return 'network — server cannot reach api.anthropic.com';
        }
        return 'unknown — see error text above; full body in storage/logs/laravel.log';
    }
}
