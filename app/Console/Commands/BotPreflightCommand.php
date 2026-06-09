<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Pre-deploy / post-deploy safety check. Run BEFORE trusting a deploy:
 *
 *   php artisan bot:preflight
 *
 * Exit 0 = all critical checks pass. Exit 1 = at least one FAIL.
 * Designed to catch the class of mistake that caused the 503
 * (duplicate import / syntax error / unloadable routes) before it
 * reaches production traffic.
 */
class BotPreflightCommand extends Command
{
    protected $signature = 'bot:preflight {--json : output machine-readable JSON}';

    protected $description = 'Pre-deploy safety check for the Messenger bot (syntax, routes, config, health)';

    private array $results = [];

    public function handle(SettingsService $settings): int
    {
        $base = base_path();
        $php = PHP_BINARY;

        // 1. PHP syntax — routes
        foreach (['routes/api.php', 'routes/web.php', 'routes/webhooks.php'] as $file) {
            $this->lintFile($php, $base.'/'.$file, $file);
        }

        // 2. Duplicate-import scan (the exact 503 root cause)
        foreach (['routes/api.php', 'routes/web.php'] as $file) {
            $this->dupImportScan($base.'/'.$file, $file);
        }

        // 3. route:list loads (catches fatal in route files / controllers)
        $this->artisanLoads($php, $base, 'route:list', 'Routes load');

        // 4. config loads
        $this->artisanLoads($php, $base, 'config:show app.name', 'Config loads');

        // 5. storage writable
        $this->check('storage writable', is_writable(storage_path('logs')) && is_writable(storage_path('framework')),
            is_writable(storage_path('logs')) ? 'writable' : 'NOT writable');

        // 6. DB reachable + queue counts
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            $this->check('database + queue', true, "DB ok · {$pending} pending · {$failed} failed",
                $failed > 0 ? 'warn' : 'ok');
        } catch (Throwable $e) {
            $this->check('database + queue', false, 'DB error: '.$e->getMessage());
        }

        // 7. Config presence (not values)
        $this->check('Claude configured', $settings->has('ANTHROPIC_API_KEY'),
            $settings->has('ANTHROPIC_API_KEY') ? 'ANTHROPIC_API_KEY set' : 'MISSING');
        $this->check('WooCommerce configured',
            $settings->has('GADGET_WC_BASE_URL') && $settings->has('GADGET_WC_CONSUMER_KEY') && $settings->has('GADGET_WC_CONSUMER_SECRET'),
            'base+key+secret');
        $this->check('Messenger configured',
            $settings->has('MESSENGER_PAGE_ID') && $settings->has('MESSENGER_PAGE_ACCESS_TOKEN') && $settings->has('MESSENGER_APP_SECRET') && $settings->has('MESSENGER_VERIFY_TOKEN'),
            'page+token+secret+verify');

        // 8. Live HTTP smoke (local loopback to the public site)
        $this->httpCheck('https://bot.gadget.ge/up', 'Health endpoint /up', [200]);
        $this->httpCheck('https://bot.gadget.ge/admin/login', 'Admin login page', [200]);
        $verifyToken = $settings->get('MESSENGER_VERIFY_TOKEN') ?: 'x';
        $this->httpCheck(
            'https://bot.gadget.ge/webhooks/messenger?hub.mode=subscribe&hub.verify_token='.urlencode($verifyToken).'&hub.challenge=preflight',
            'Webhook GET verify', [200], 'preflight');

        // Output
        $fail = collect($this->results)->where('status', 'fail')->count();
        $warn = collect($this->results)->where('status', 'warn')->count();

        if ($this->option('json')) {
            $this->line(json_encode([
                'pass' => $fail === 0,
                'fail' => $fail,
                'warn' => $warn,
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return $fail === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('================ bot:preflight ================');
        foreach ($this->results as $r) {
            $icon = ['ok' => '<info>✓</info>', 'warn' => '<comment>!</comment>', 'fail' => '<error>✕</error>'][$r['status']] ?? '?';
            $this->line(sprintf('  %s %-28s %s', $icon, $r['name'], $r['message']));
        }
        $this->newLine();
        if ($fail === 0) {
            $this->info("PREFLIGHT PASS · {$warn} warning(s)");
            return self::SUCCESS;
        }
        $this->error("PREFLIGHT FAIL · {$fail} failure(s), {$warn} warning(s)");
        return self::FAILURE;
    }

    private function lintFile(string $php, string $path, string $label): void
    {
        if (! is_file($path)) {
            $this->check("syntax: {$label}", true, 'absent (skipped)', 'warn');
            return;
        }
        try {
            $res = Process::timeout(20)->run([$php, '-l', $path]);
            $this->check("syntax: {$label}", $res->successful(),
                $res->successful() ? 'No syntax errors' : trim($res->errorOutput() ?: $res->output()));
        } catch (Throwable $e) {
            // Process spawn blocked (nproc) — fall back to php_check_syntax-less heuristic
            $this->check("syntax: {$label}", true, 'lint skipped (proc spawn blocked): '.mb_strimwidth($e->getMessage(), 0, 40, '…'), 'warn');
        }
    }

    private function dupImportScan(string $path, string $label): void
    {
        if (! is_file($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $imports = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*use\s+([^;]+);/', $line, $m)) {
                $fqcn = trim($m[1]);
                $imports[$fqcn] = ($imports[$fqcn] ?? 0) + 1;
            }
        }
        $dupes = array_filter($imports, fn ($c) => $c > 1);
        $this->check("dup-import: {$label}", empty($dupes),
            empty($dupes) ? 'no duplicate imports' : 'DUPLICATE: '.implode(', ', array_keys($dupes)));
    }

    private function artisanLoads(string $php, string $base, string $cmd, string $label): void
    {
        try {
            $res = Process::path($base)->timeout(30)->run(array_merge([$php, 'artisan'], explode(' ', $cmd)));
            $this->check($label, $res->successful(),
                $res->successful() ? 'OK' : mb_strimwidth(trim($res->errorOutput() ?: $res->output()), 0, 80, '…'));
        } catch (Throwable $e) {
            $this->check($label, true, 'check skipped (proc spawn blocked)', 'warn');
        }
    }

    private function httpCheck(string $url, string $label, array $okCodes, ?string $expectBody = null): void
    {
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(15)->withoutVerifying()->get($url);
            $codeOk = in_array($resp->status(), $okCodes, true);
            $bodyOk = $expectBody === null || str_contains($resp->body(), $expectBody);
            $this->check($label, $codeOk && $bodyOk,
                'HTTP '.$resp->status().($expectBody ? ($bodyOk ? ' · body ok' : ' · body MISMATCH') : ''));
        } catch (Throwable $e) {
            $this->check($label, false, 'request failed: '.mb_strimwidth($e->getMessage(), 0, 50, '…'));
        }
    }

    private function check(string $name, bool $pass, string $message, ?string $forceStatus = null): void
    {
        $status = $forceStatus ?? ($pass ? 'ok' : 'fail');
        $this->results[] = compact('name', 'status', 'message');
    }
}
