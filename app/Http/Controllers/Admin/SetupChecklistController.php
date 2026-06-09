<?php

namespace App\Http\Controllers\Admin;

use App\Services\SettingsService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SetupChecklistController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index()
    {
        return response()->json([
            'items' => [
                $this->domain(),
                $this->ssl(),
                $this->database(),
                $this->queue(),
                $this->webhooks(),
                $this->whatsapp(),
                $this->messenger(),
                $this->instagram(),
                $this->woocommerce(),
                $this->ai(),
                $this->payment(),
                $this->escalation(),
            ],
        ]);
    }

    private function item(string $key, string $title, string $status, string $message, ?string $hint = null): array
    {
        return compact('key', 'title', 'status', 'message', 'hint');
    }

    private function domain(): array
    {
        $host = request()->getHost();
        return $this->item('domain', 'Domain', 'ok', $host, 'cPanel-ში subdomain სწორად კონფიგურირებულია');
    }

    private function ssl(): array
    {
        // We're running on the same host that serves https://bot.gadget.ge
        $host = request()->getHost();
        try {
            $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false]]);
            $client = @stream_socket_client("ssl://{$host}:443", $errno, $err, 5, STREAM_CLIENT_CONNECT, $ctx);
            if (! $client) {
                return $this->item('ssl', 'SSL', 'fail', 'TLS handshake ვერ მოხერხდა: '.$err);
            }
            $params = stream_context_get_params($client);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;
            fclose($client);
            if (! $cert) {
                return $this->item('ssl', 'SSL', 'fail', 'cert ვერ მივიღეთ');
            }
            $parsed = openssl_x509_parse($cert);
            $expires = (int) ($parsed['validTo_time_t'] ?? 0);
            if ($expires < time()) {
                return $this->item('ssl', 'SSL', 'fail', 'ცერტი ვადაგასულია '.date('Y-m-d', $expires));
            }
            $daysLeft = (int) (($expires - time()) / 86400);
            $cn = $parsed['subject']['CN'] ?? '?';
            $status = $daysLeft < 14 ? 'warn' : 'ok';
            return $this->item('ssl', 'SSL', $status, "CN={$cn} · ვადა {$daysLeft} დღე");
        } catch (Throwable $e) {
            return $this->item('ssl', 'SSL', 'fail', $e->getMessage());
        }
    }

    private function database(): array
    {
        try {
            $version = DB::selectOne('SELECT VERSION() AS v')->v ?? '?';
            $tables = Schema::hasTable('conversations') && Schema::hasTable('jobs') && Schema::hasTable('employees');
            if (! $tables) {
                return $this->item('database', 'Database', 'fail', 'მიგრაცია არ გავიდა');
            }
            return $this->item('database', 'Database', 'ok', 'MariaDB '.$version);
        } catch (Throwable $e) {
            return $this->item('database', 'Database', 'fail', $e->getMessage());
        }
    }

    private function queue(): array
    {
        try {
            $jobs = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            if ($failed > 0) {
                return $this->item('queue', 'Queue', 'warn', "queue={$jobs} jobs · failed={$failed}", 'შეამოწმე failed_jobs ცხრილი');
            }
            return $this->item('queue', 'Queue', 'ok', "queue={$jobs} jobs · failed=0");
        } catch (Throwable $e) {
            return $this->item('queue', 'Queue', 'fail', $e->getMessage());
        }
    }

    private function webhooks(): array
    {
        // We don't track verified-state per-channel; consider verified when the
        // matching verify_token is set.
        $verified = collect(['WHATSAPP_VERIFY_TOKEN', 'MESSENGER_VERIFY_TOKEN', 'INSTAGRAM_VERIFY_TOKEN'])
            ->filter(fn($k) => $this->settings->has($k))->count();
        if ($verified === 0) {
            return $this->item('webhooks', 'Webhooks', 'pending', 'არცერთი verify_token არ არის');
        }
        if ($verified < 3) {
            return $this->item('webhooks', 'Webhooks', 'warn', "{$verified}/3 channel-ს აქვს verify_token");
        }
        return $this->item('webhooks', 'Webhooks', 'ok', '3/3 channel verify_token configured');
    }

    private function whatsapp(): array
    {
        $required = SettingsService::GROUPS['whatsapp'];
        $set = collect($required)->filter(fn($k) => $this->settings->has($k))->count();
        if ($set === 0) return $this->item('whatsapp', 'WhatsApp', 'pending', 'არ არის კონფიგურირებული');
        if ($set < count($required)) return $this->item('whatsapp', 'WhatsApp', 'warn', "{$set}/".count($required).' ფილდი შევსებულია');
        return $this->item('whatsapp', 'WhatsApp', 'ok', 'ყველა ფილდი შევსებულია');
    }

    private function messenger(): array
    {
        $required = SettingsService::GROUPS['messenger'];
        $set = collect($required)->filter(fn($k) => $this->settings->has($k))->count();
        if ($set === 0) return $this->item('messenger', 'Messenger', 'pending', 'არ არის კონფიგურირებული');
        if ($set < count($required)) return $this->item('messenger', 'Messenger', 'warn', "{$set}/".count($required).' ფილდი შევსებულია');
        return $this->item('messenger', 'Messenger', 'ok', 'ყველა ფილდი შევსებულია');
    }

    private function instagram(): array
    {
        $required = SettingsService::GROUPS['instagram'];
        $set = collect($required)->filter(fn($k) => $this->settings->has($k))->count();
        if ($set === 0) return $this->item('instagram', 'Instagram', 'pending', 'არ არის კონფიგურირებული');
        if ($set < count($required)) return $this->item('instagram', 'Instagram', 'warn', "{$set}/".count($required).' ფილდი შევსებულია');
        return $this->item('instagram', 'Instagram', 'ok', 'ყველა ფილდი შევსებულია');
    }

    private function woocommerce(): array
    {
        $required = ['GADGET_WC_BASE_URL', 'GADGET_WC_CONSUMER_KEY', 'GADGET_WC_CONSUMER_SECRET'];
        $set = collect($required)->filter(fn($k) => $this->settings->has($k))->count();
        if ($set === 0) return $this->item('woocommerce', 'WooCommerce', 'pending', 'არ არის კონფიგურირებული');
        if ($set < count($required)) return $this->item('woocommerce', 'WooCommerce', 'warn', "{$set}/".count($required).' ფილდი შევსებულია');
        return $this->item('woocommerce', 'WooCommerce', 'ok', 'WC credentials მზადაა');
    }

    private function ai(): array
    {
        if (! $this->settings->has('ANTHROPIC_API_KEY')) {
            return $this->item('ai', 'AI (Claude)', 'fail', 'ANTHROPIC_API_KEY არ არის — AI replies არ იმუშავებს');
        }
        return $this->item('ai', 'AI (Claude)', 'ok', 'API key მზადაა');
    }

    private function payment(): array
    {
        if (! $this->settings->has('PAYMENT_API_KEY')) {
            return $this->item('payment', 'Payment (BOG)', 'pending', 'არ არის კონფიგურირებული');
        }
        return $this->item('payment', 'Payment (BOG)', 'ok', 'BOG credentials მზადაა');
    }

    private function escalation(): array
    {
        $to = $this->settings->get('ESCALATION_WHATSAPP_TO');
        if (! $to || $to === '995599000000') {
            return $this->item('escalation', 'Escalation', 'warn', 'placeholder ნომერი — შეცვალე ნამდვილით');
        }
        return $this->item('escalation', 'Escalation', 'ok', $to);
    }
}
