<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Gadget\WooCommerceClient;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnostic for the WooCommerce / gadget.ge integration.
 *
 * Prints exactly which source each setting is read from (DB / .env /
 * config / not_set), masked secrets, the resolved REST endpoint, then
 * performs a live API round-trip to /wp-json/wc/v3/products?per_page=2.
 * Detects: 401/403 auth issues, HTML responses (login page / WAF block),
 * empty catalogs, etc. Designed to be safe to paste into a ticket.
 *
 * Usage:
 *   php artisan wc:check-config
 *   php artisan wc:check-config --no-call
 *   php artisan wc:check-config --search="iphone"
 */
class WcCheckConfigCommand extends Command
{
    protected $signature = 'wc:check-config
        {--no-call : skip the live API round-trip}
        {--search= : test a specific search query}';
    protected $description = 'Inspect WooCommerce settings + verify reachability';

    public function handle(SettingsService $settings, WooCommerceClient $wc): int
    {
        $this->line('================ WooCommerce configuration check ================');

        $resolved = [];
        foreach ([
            'GADGET_WC_BASE_URL' => 'base_url',
            'GADGET_WC_API_PATH' => 'api_path',
            'GADGET_WC_CONSUMER_KEY' => 'consumer_key',
            'GADGET_WC_CONSUMER_SECRET' => 'consumer_secret',
            'GADGET_WC_WEBHOOK_SECRET' => 'webhook_secret',
        ] as $key => $cfgKey) {
            $db = Setting::where('key', $key)->first()?->value;
            $env = env($key) ?: null;
            $cfg = config('gadget.'.$cfgKey) ?: null;
            $resolved[$key] = $settings->get($key);

            $source = !empty($db) ? 'db' : (!empty($env) ? 'env' : (!empty($cfg) ? 'config' : 'not_set'));
            $val = $resolved[$key];
            $isSecret = in_array($key, ['GADGET_WC_CONSUMER_SECRET', 'GADGET_WC_WEBHOOK_SECRET']);
            $display = $val ? ($isSecret ? $this->mask($val) : $val) : '(empty)';

            $tag = match ($source) {
                'db' => '<info>db</info>',
                'env' => '<comment>env</comment>',
                'config' => '<comment>config</comment>',
                default => '<error>not_set</error>',
            };
            $this->line(sprintf('  %-28s %-12s %s', $key, "[$source]", $display));
        }

        $base = $resolved['GADGET_WC_BASE_URL'] ?: 'https://gadget.ge';
        $path = $resolved['GADGET_WC_API_PATH'] ?: '/wp-json/wc/v3';
        $endpoint = rtrim($base, '/').rtrim($path, '/');

        $this->line('');
        $this->line('  Resolved REST endpoint:   '.$endpoint);

        // Sanity checks
        $issues = [];
        if (! $resolved['GADGET_WC_BASE_URL']) {
            $issues[] = 'base_url ცარიელია — set GADGET_WC_BASE_URL';
        } elseif (! str_starts_with($resolved['GADGET_WC_BASE_URL'], 'https://')) {
            $issues[] = 'base_url არ იწყება "https://" — WC REST API HTTPS-ს მოითხოვს';
        }
        if (! $resolved['GADGET_WC_CONSUMER_KEY']) {
            $issues[] = 'consumer_key ცარიელია';
        } elseif (! str_starts_with($resolved['GADGET_WC_CONSUMER_KEY'], 'ck_')) {
            $issues[] = 'consumer_key არ იწყება "ck_" — შემოწმე format';
        }
        if (! $resolved['GADGET_WC_CONSUMER_SECRET']) {
            $issues[] = 'consumer_secret ცარიელია';
        } elseif (! str_starts_with($resolved['GADGET_WC_CONSUMER_SECRET'], 'cs_')) {
            $issues[] = 'consumer_secret არ იწყება "cs_" — შემოწმე format';
        }

        if ($issues) {
            $this->line('');
            $this->error('Format issues:');
            foreach ($issues as $i) $this->error('  ✗ '.$i);
        }

        if ($this->option('no-call')) {
            $this->line('');
            $this->info('--no-call set; skipping live API round-trip.');
            return $issues ? self::FAILURE : self::SUCCESS;
        }

        if (! $resolved['GADGET_WC_BASE_URL'] || ! $resolved['GADGET_WC_CONSUMER_KEY'] || ! $resolved['GADGET_WC_CONSUMER_SECRET']) {
            $this->line('');
            $this->error('Cannot make API call — credentials incomplete.');
            return self::FAILURE;
        }

        // Round-trip
        $search = $this->option('search');
        $params = ['per_page' => 2];
        if ($search) $params['search'] = $search;

        $this->line('');
        $this->line('  Round-trip test ('.($search ? "search=\"$search\"" : 'list 2').'):');
        try {
            $resp = $wc->get('products', $params);

            if (! is_array($resp)) {
                $this->error('    ✗ non-array response: '.gettype($resp));
                return self::FAILURE;
            }
            // Error envelope detection (WC returns {code, message, data} on error)
            if (isset($resp['code']) && isset($resp['message'])) {
                $this->error('    ✗ WC error: '.$resp['code']);
                $this->line('    message: '.$resp['message']);
                $this->line('    hint: '.$this->hintForCode($resp['code']));
                return self::FAILURE;
            }
            // HTML detection (login page, WAF block)
            if (isset($resp['raw'])) {
                $body = substr((string) $resp['raw'], 0, 200);
                $this->error('    ✗ Non-JSON response (likely HTML / login / WAF block)');
                $this->line('    body[0:200]: '.$body);
                $this->line('    hint: '.($this->guessHtmlCause($body)));
                return self::FAILURE;
            }
            // Real product array
            $count = count($resp);
            $this->info('    ✓ HTTP 200, '.$count.' product(s) returned');
            foreach ($resp as $i => $p) {
                if (! is_array($p) || ! isset($p['id'])) continue;
                $this->line(sprintf(
                    "    [%d] id=%-6d sku=%-10s · %s · %s%s · %s",
                    $i,
                    $p['id'],
                    $p['sku'] ?: '-',
                    mb_strimwidth($p['name'] ?? '', 0, 50, '…'),
                    $p['price'] ?? '?',
                    $p['currency'] ?? '₾',
                    $p['stock_status'] ?? '?',
                ));
                if (! empty($p['images'][0]['src'])) {
                    $this->line('         image: '.$p['images'][0]['src']);
                }
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('    ✗ API call failed');
            $this->line('    error: '.$e->getMessage());
            $this->line('    hint:  '.$this->guessExceptionCause($e->getMessage()));
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

    private function hintForCode(string $code): string
    {
        return match ($code) {
            'woocommerce_rest_authentication_error',
            'woocommerce_rest_cannot_view' => 'invalid_credentials — გადააგენერირე WC REST API key with Read/Write',
            'woocommerce_rest_term_invalid' => 'wrong endpoint — შემოწმე GADGET_WC_API_PATH',
            'rest_no_route' => 'no_such_route — შემოწმე base URL და /wp-json/wc/v3 path',
            default => 'see WC docs for code='.$code,
        };
    }

    private function guessHtmlCause(string $body): string
    {
        $low = strtolower($body);
        if (str_contains($low, 'cloudflare')) return 'Cloudflare blocked the request — whitelist server IP';
        if (str_contains($low, 'wp-login') || str_contains($low, 'login_form')) return 'WordPress login page — WC permalinks broken or REST API disabled';
        if (str_contains($low, 'maintenance')) return 'site in maintenance mode';
        if (str_contains($low, 'security') || str_contains($low, 'wordfence') || str_contains($low, 'sucuri')) return 'security plugin blocked the request';
        if (str_contains($low, '<!doctype')) return 'HTML response — REST API returns HTML instead of JSON (permalinks issue?)';
        return 'unknown HTML — see body excerpt above';
    }

    private function guessExceptionCause(string $msg): string
    {
        $low = strtolower($msg);
        if (str_contains($low, 'connect')) return 'cannot reach base URL — DNS / firewall';
        if (str_contains($low, 'ssl') || str_contains($low, 'cert')) return 'SSL cert issue on gadget.ge';
        if (str_contains($low, 'timeout')) return 'WC server slow — try again or check WP performance';
        return 'see laravel.log for stack trace';
    }
}
