<?php

namespace App\Http\Controllers\Admin;

use App\Models\Message;
use App\Services\SettingsService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only Meta/Messenger readiness probe for App Review preparation.
 * Every Graph call here is a GET — this controller NEVER sends a message.
 */
class MetaReadinessController extends Controller
{
    private const GRAPH_VERSION = 'v23.0';

    public function __construct(private SettingsService $settings) {}

    public function index()
    {
        $pageId = $this->settings->get('MESSENGER_PAGE_ID');
        $token = $this->settings->get('MESSENGER_PAGE_ACCESS_TOKEN');
        $appSecret = $this->settings->get('MESSENGER_APP_SECRET');
        $verifyToken = $this->settings->get('MESSENGER_VERIFY_TOKEN');

        $permissions = $this->probePermissions($pageId, $token);
        $subscription = $this->probeSubscription($token);
        $lastPost = $this->lastMetaPost();
        $lastSend = $this->lastOutboundSend();

        // App Review checklist — derived from probes
        $checklist = [
            $this->ck('webhook_verify', 'Webhook verify token', $verifyToken ? 'ok' : 'fail',
                $verifyToken ? 'configured' : 'MESSENGER_VERIFY_TOKEN ცარიელია'),
            $this->ck('app_secret', 'App Secret (signature)', $appSecret ? 'ok' : 'fail',
                $appSecret ? 'configured — POST signature verified' : 'MESSENGER_APP_SECRET ცარიელია → POST 403'),
            $this->ck('page_token', 'Page Access Token', $token ? 'ok' : 'fail',
                $token ? 'configured (len='.strlen((string) $token).')' : 'არ არის'),
            $this->ck('page_id', 'Messenger Page ID', $pageId ? 'ok' : 'fail', $pageId ?: 'არ არის'),
            $this->ck('perm_messaging', 'pages_messaging', $permissions['pages_messaging']['status'],
                $permissions['pages_messaging']['message']),
            $this->ck('perm_metadata', 'pages_manage_metadata', $permissions['pages_manage_metadata']['status'],
                $permissions['pages_manage_metadata']['message']),
            $this->ck('perm_read_engagement', 'pages_read_engagement', $permissions['pages_read_engagement']['status'],
                $permissions['pages_read_engagement']['message']),
            $this->ck('perm_show_list', 'pages_show_list', $permissions['pages_show_list']['status'],
                $permissions['pages_show_list']['message']),
            $this->ck('subscription', 'Webhook subscription', $subscription['status'], $subscription['message']),
            $this->ck('last_inbound', 'Last inbound POST', $lastPost ? 'ok' : 'pending',
                $lastPost ? $lastPost['ts'].' from PSID '.$lastPost['psid'] : 'არცერთი POST ჯერ'),
            $this->ck('last_outbound', 'Last outbound send', $lastSend ? 'ok' : 'pending',
                $lastSend ? $lastSend['ts'].' ('.$lastSend['kind'].')' : 'არცერთი outbound ჯერ'),
        ];

        // Overall readiness — fail if any hard requirement (token/secret/perm_messaging) fails
        $hardKeys = ['webhook_verify', 'app_secret', 'page_token', 'page_id', 'perm_messaging'];
        $ready = collect($checklist)
            ->whereIn('key', $hardKeys)
            ->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'ready_for_review' => $ready,
            'app_mode_note' => 'Meta App mode (Development/Live) ვერ მოწმდება Page token-ით — შეამოწმე developers.facebook.com → App → top toggle.',
            'webhook_url' => 'https://bot.gadget.ge/webhooks/messenger',
            'page_id' => $pageId,
            'verify_token_set' => (bool) $verifyToken,
            'permissions' => $permissions,
            'subscription' => $subscription,
            'last_inbound_post' => $lastPost,
            'last_outbound_send' => $lastSend,
            'checklist' => $checklist,
            'computed_at' => now()->toIso8601String(),
        ]);
    }

    private function ck(string $key, string $title, string $status, string $message): array
    {
        return compact('key', 'title', 'status', 'message');
    }

    /**
     * Probe each permission with a GET endpoint that requires it.
     * Read-only — sends nothing.
     */
    private function probePermissions(?string $pageId, ?string $token): array
    {
        $base = 'https://graph.facebook.com/'.self::GRAPH_VERSION;
        $out = [
            'pages_messaging' => ['status' => 'pending', 'message' => 'token არ არის'],
            'pages_manage_metadata' => ['status' => 'pending', 'message' => 'token არ არის'],
            'pages_read_engagement' => ['status' => 'pending', 'message' => 'token არ არის'],
            'pages_show_list' => ['status' => 'pending', 'message' => 'token არ არის'],
        ];
        if (! $token) return $out;

        // pages_messaging → /me/messenger_profile (read)
        $out['pages_messaging'] = $this->probe($base.'/me/messenger_profile', ['fields' => 'whitelisted_domains', 'access_token' => $token], 'pages_messaging');
        // pages_manage_metadata → /me/subscribed_apps
        $out['pages_manage_metadata'] = $this->probe($base.'/me/subscribed_apps', ['access_token' => $token], 'pages_manage_metadata');
        // pages_read_engagement → /me?fields=name
        $out['pages_read_engagement'] = $this->probe($base.'/me', ['fields' => 'name,id', 'access_token' => $token], 'pages_read_engagement');
        // pages_show_list → /me/accounts (lists pages the token can manage)
        $out['pages_show_list'] = $this->probe($base.'/me/accounts', ['access_token' => $token], 'pages_show_list');

        return $out;
    }

    private function probe(string $url, array $query, string $perm): array
    {
        try {
            $resp = Http::timeout(10)->get($url, $query);
            if ($resp->successful()) {
                return ['status' => 'ok', 'message' => 'მუშაობს ('.$perm.' granted)'];
            }
            $err = $resp->json('error') ?? [];
            $code = (int) ($err['code'] ?? $resp->status());
            $msg = (string) ($err['message'] ?? 'http_'.$resp->status());
            // #200/#10 = permission/review needed; #100 = missing perm
            $status = in_array($code, [10, 200, 100], true) ? 'fail' : 'warn';
            return ['status' => $status, 'message' => '#'.$code.' '.mb_strimwidth($msg, 0, 80, '…')];
        } catch (Throwable $e) {
            return ['status' => 'warn', 'message' => 'ქსელის შეცდომა: '.mb_strimwidth($e->getMessage(), 0, 60, '…')];
        }
    }

    private function probeSubscription(?string $token): array
    {
        if (! $token) return ['status' => 'pending', 'message' => 'token არ არის'];
        try {
            $resp = Http::timeout(10)->get('https://graph.facebook.com/'.self::GRAPH_VERSION.'/me/subscribed_apps', [
                'access_token' => $token,
            ]);
            if ($resp->successful()) {
                $data = $resp->json('data') ?? [];
                if (empty($data)) {
                    return ['status' => 'warn', 'message' => 'არცერთი app subscribed — Meta App → Messenger → Webhooks → Add Subscription'];
                }
                $fields = [];
                foreach ($data as $app) {
                    foreach (($app['subscribed_fields'] ?? []) as $f) $fields[] = $f;
                }
                $fields = array_values(array_unique($fields));
                $hasMessages = in_array('messages', $fields, true);
                return [
                    'status' => $hasMessages ? 'ok' : 'warn',
                    'message' => ($hasMessages ? 'subscribed: ' : 'messages NOT subscribed! fields: ').implode(', ', $fields),
                ];
            }
            $err = $resp->json('error.message') ?? 'http_'.$resp->status();
            return ['status' => 'warn', 'message' => 'ვერ წავიკითხე subscription: '.mb_strimwidth((string) $err, 0, 70, '…')];
        } catch (Throwable $e) {
            return ['status' => 'warn', 'message' => 'ქსელის შეცდომა'];
        }
    }

    private function lastMetaPost(): ?array
    {
        $path = storage_path('logs/messenger-webhook-raw.log');
        if (! is_file($path)) return null;
        $fp = @fopen($path, 'r');
        if (! $fp) return null;
        fseek($fp, 0, SEEK_END);
        $size = ftell($fp);
        $offset = max(0, $size - 65_536);
        fseek($fp, $offset);
        $tail = fread($fp, $size - $offset);
        fclose($fp);

        // Find last POST block with a sender id
        if (preg_match_all('/\[([^\]]+)\] POST .*?"sender":\{"id":"(\d+)"/s', $tail, $m, PREG_SET_ORDER)) {
            $last = end($m);
            return ['ts' => $last[1], 'psid' => $last[2]];
        }
        return null;
    }

    private function lastOutboundSend(): ?array
    {
        $msg = Message::where('direction', Message::DIRECTION_OUT)
            ->whereHas('conversation', fn ($q) => $q->where('platform', 'messenger'))
            ->latest('id')
            ->first();
        if (! $msg) return null;
        return [
            'ts' => $msg->created_at?->toIso8601String(),
            'kind' => $msg->is_ai ? 'AI auto-reply' : 'manual/agent',
        ];
    }
}
