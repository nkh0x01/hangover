<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class IntegrationTester
{
    public function __construct(private SettingsService $settings) {}

    /** @return array{success:bool, message:string, detail?:mixed} */
    public function test(string $type): array
    {
        return match ($type) {
            'whatsapp'    => $this->testWhatsapp(),
            'messenger'   => $this->testMessenger(),
            'instagram'   => $this->testInstagram(),
            'woocommerce' => $this->testWoocommerce(),
            'payment'     => $this->testPayment(),
            'ai'          => $this->testAi(),
            'escalation'  => $this->testEscalation(),
            default       => ['success' => false, 'message' => 'unknown integration type'],
        };
    }

    private function testWhatsapp(): array
    {
        $phoneId = $this->settings->get('WHATSAPP_PHONE_NUMBER_ID');
        $token = $this->settings->get('WHATSAPP_ACCESS_TOKEN');
        if (! $phoneId || ! $token) {
            return ['success' => false, 'message' => 'WHATSAPP_PHONE_NUMBER_ID ან WHATSAPP_ACCESS_TOKEN ცარიელია'];
        }
        try {
            $resp = Http::timeout(10)
                ->withToken($token)
                ->get("https://graph.facebook.com/v20.0/{$phoneId}");
            if ($resp->successful()) {
                $data = $resp->json();
                return ['success' => true, 'message' => 'დაკავშირდა · '.($data['display_phone_number'] ?? $data['verified_name'] ?? 'OK'), 'detail' => $data];
            }
            return ['success' => false, 'message' => 'Graph API უარყო ('.$resp->status().')', 'detail' => $resp->json()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'შეცდომა: '.$e->getMessage()];
        }
    }

    private function testMessenger(): array
    {
        $pageId = $this->settings->get('MESSENGER_PAGE_ID');
        $token = $this->settings->get('MESSENGER_PAGE_ACCESS_TOKEN');
        $version = 'v23.0'; // Messenger Platform-ის უახლესი stable, არ ეხება WhatsApp-ს

        if (! $pageId) return ['success' => false, 'message' => 'MESSENGER_PAGE_ID ცარიელია'];
        if (! $token)  return ['success' => false, 'message' => 'MESSENGER_PAGE_ACCESS_TOKEN ცარიელია'];

        $base = "https://graph.facebook.com/{$version}";

        try {
            // ნაბიჯი 1: /me?fields=id,name — საუკეთესო case (token-ის Page ID-ს ვიგებთ)
            $r1 = Http::timeout(10)->get("{$base}/me", [
                'fields' => 'id,name',
                'access_token' => $token,
            ]);

            if ($r1->successful()) {
                $body = $r1->json();
                $tokenPageId = (string) ($body['id'] ?? '');
                $tokenPageName = (string) ($body['name'] ?? '?');
                if ($tokenPageId !== (string) $pageId) {
                    return [
                        'success' => false,
                        'message' => "Token სხვა Page-ისაა (token: {$tokenPageId} '{$tokenPageName}', საჭიროა: {$pageId})",
                        'detail' => ['token_page' => $body, 'configured_page_id' => $pageId],
                    ];
                }
                return [
                    'success' => true,
                    'message' => "✓ დაკავშირდა · {$tokenPageName} ({$tokenPageId})",
                    'detail' => $body,
                ];
            }

            // ნაბიჯი 2: Map primary error
            $err1 = $r1->json('error') ?? [];
            $code1 = (int) ($err1['code'] ?? 0);

            // Token level issues — არ ვაგრძელებთ probe-ებს
            if ($code1 === 190 || $code1 === 102) {
                return [
                    'success' => false,
                    'message' => $this->mapMessengerError($code1, $err1, false, false),
                    'detail' => ['error' => $err1, 'endpoint' => "{$base}/me?fields=id,name"],
                ];
            }

            // ნაბიჯი 3: ცალკე probe pages_messaging (needs pages_messaging)
            $r2 = Http::timeout(10)->get("{$base}/me/messenger_profile", [
                'fields' => 'whitelisted_domains',
                'access_token' => $token,
            ]);
            $hasMessaging = $r2->successful();

            // ნაბიჯი 4: ცალკე probe pages_manage_metadata (needs pages_manage_metadata, საჭიროა webhook subscribe-ისთვის)
            $r3 = Http::timeout(10)->get("{$base}/me/subscribed_apps", [
                'access_token' => $token,
            ]);
            $hasManageMetadata = $r3->successful();

            return [
                'success' => false,
                'message' => $this->mapMessengerError($code1, $err1, $hasMessaging, $hasManageMetadata),
                'detail' => [
                    'primary_error' => $err1,
                    'probe_pages_messaging' => [
                        'endpoint' => "{$base}/me/messenger_profile",
                        'http_status' => $r2->status(),
                        'works' => $hasMessaging,
                        'error' => $hasMessaging ? null : ($r2->json('error') ?? null),
                    ],
                    'probe_pages_manage_metadata' => [
                        'endpoint' => "{$base}/me/subscribed_apps",
                        'http_status' => $r3->status(),
                        'works' => $hasManageMetadata,
                        'subscribed' => $hasManageMetadata ? ($r3->json('data') ?? []) : null,
                        'error' => $hasManageMetadata ? null : ($r3->json('error') ?? null),
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'ქსელის შეცდომა: '.$e->getMessage()];
        }
    }

    private function mapMessengerError(int $code, array $err, bool $hasMessaging, bool $hasManageMetadata): string
    {
        $msg = (string) ($err['message'] ?? '');
        $sub = (int) ($err['error_subcode'] ?? 0);

        // Invalid / expired token
        if ($code === 190) {
            if ($sub === 463 || $sub === 466) return 'Access token ვადაგასულია — გენერირე ახალი Meta-ში → Messenger API Settings → Generate Token';
            if (str_contains($msg, 'expired')) return 'Access token ვადაგასულია — გენერირე ახალი';
            return 'Access token არასწორი — გადაამოწმე და ხელახლა შეინახე';
        }
        if ($code === 102) return 'Session expired — გენერირე ახალი Page Access Token Meta-ში';

        // Permission issues
        if ($code === 100) {
            if (str_contains($msg, 'pages_read_engagement')) {
                if ($hasMessaging && $hasManageMetadata) {
                    return '✓ pages_messaging + pages_manage_metadata მუშაობს. ⚠️ მხოლოდ pages_read_engagement ცარიელია — Messenger DM-ები იმუშავებს, basic readback test ვერ ჩავატარეთ';
                }
                if ($hasMessaging && ! $hasManageMetadata) {
                    return '✓ pages_messaging მუშაობს. ⚠️ pages_manage_metadata ცარიელია — webhook subscribe-ი ვერ მოხერხდება პროგრამულად, ხელით უნდა გააკეთო Meta App Dashboard-ში';
                }
                if (! $hasMessaging && ! $hasManageMetadata) {
                    return 'Token-ს არ აქვს pages_messaging — Messenger API ვერ მუშაობს. Meta App-ში დაამატე pages_messaging permission და ხელახლა გენერირე Page Access Token';
                }
                return 'pages_messaging გვაქვს, მაგრამ რაღაც სხვა ჩავარდა — იხილე detail';
            }
            if (str_contains($msg, 'Object does not exist')) {
                return 'Page ID არ მოიძებნა ან Token-ი მასზე წვდომა არ აქვს — შეამოწმე MESSENGER_PAGE_ID';
            }
            return 'Graph API #100: '.$msg;
        }
        if ($code === 200) return 'App-ს App Review არ აქვს გავლილი ამ permission-ისთვის — Live mode-ში გადადი ან Test User გამოიყენე';
        if ($code === 10) return 'App ვერ ისარგებლებს ამ feature-ით — App Review სავალდებულოა';
        if ($code === 4 || $code === 17) return 'Rate limit — სცადე 5 წუთში';
        if ($code === 803) return 'Page არასწორი ან წაშლილია';

        return 'Graph API #'.$code.': '.$msg;
    }

    private function testInstagram(): array
    {
        $igId = $this->settings->get('INSTAGRAM_ACCOUNT_ID');
        $token = $this->settings->get('INSTAGRAM_ACCESS_TOKEN');
        if (! $igId || ! $token) {
            return ['success' => false, 'message' => 'INSTAGRAM_ACCOUNT_ID ან INSTAGRAM_ACCESS_TOKEN ცარიელია'];
        }
        try {
            $resp = Http::timeout(10)
                ->withToken($token)
                ->get("https://graph.facebook.com/v20.0/{$igId}", ['fields' => 'username,id']);
            if ($resp->successful()) {
                return ['success' => true, 'message' => 'დაკავშირდა · @'.($resp->json('username') ?? 'OK'), 'detail' => $resp->json()];
            }
            return ['success' => false, 'message' => 'Graph API უარყო ('.$resp->status().')', 'detail' => $resp->json()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'შეცდომა: '.$e->getMessage()];
        }
    }

    private function testWoocommerce(): array
    {
        $base = $this->settings->get('GADGET_WC_BASE_URL');
        $key = $this->settings->get('GADGET_WC_CONSUMER_KEY');
        $secret = $this->settings->get('GADGET_WC_CONSUMER_SECRET');
        if (! $base || ! $key || ! $secret) {
            return ['success' => false, 'message' => 'WooCommerce credentials არასრულია'];
        }
        try {
            $resp = Http::timeout(15)
                ->withBasicAuth($key, $secret)
                ->get(rtrim($base, '/').'/wp-json/wc/v3/system_status');
            if ($resp->successful()) {
                $env = $resp->json('environment') ?? [];
                return ['success' => true, 'message' => 'WooCommerce '.($env['version'] ?? 'OK').' · '.($env['site_url'] ?? '')];
            }
            return ['success' => false, 'message' => 'WC API უარყო ('.$resp->status().')'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'შეცდომა: '.$e->getMessage()];
        }
    }

    private function testPayment(): array
    {
        $key = $this->settings->get('PAYMENT_API_KEY');
        $secret = $this->settings->get('PAYMENT_API_SECRET');
        if (! $key || ! $secret) {
            return ['success' => false, 'message' => 'PAYMENT_API_KEY ან PAYMENT_API_SECRET ცარიელია'];
        }
        // Format-only validation for now (no real BOG endpoint hit until live)
        return ['success' => true, 'message' => 'credentials შენახულია (real BOG test endpoint TBD)'];
    }

    private function testAi(): array
    {
        $key = $this->settings->get('ANTHROPIC_API_KEY');
        $model = $this->settings->get('ANTHROPIC_MODEL_LIGHT') ?: 'claude-haiku-4-5';
        if (! $key) {
            return ['success' => false, 'message' => 'ANTHROPIC_API_KEY ცარიელია'];
        }
        try {
            $resp = Http::timeout(20)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 8,
                    'messages' => [['role' => 'user', 'content' => 'Reply with just OK']],
                ]);
            if ($resp->successful()) {
                $text = $resp->json('content.0.text') ?? '';
                return ['success' => true, 'message' => 'Claude პასუხობს · '.$model.' · "'.trim($text).'"'];
            }
            return ['success' => false, 'message' => 'Anthropic უარყო ('.$resp->status().')', 'detail' => $resp->json('error') ?? $resp->json()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'შეცდომა: '.$e->getMessage()];
        }
    }

    private function testEscalation(): array
    {
        $to = $this->settings->get('ESCALATION_WHATSAPP_TO');
        $phoneId = $this->settings->get('WHATSAPP_PHONE_NUMBER_ID');
        $token = $this->settings->get('WHATSAPP_ACCESS_TOKEN');
        if (! $to) {
            return ['success' => false, 'message' => 'ESCALATION_WHATSAPP_TO ცარიელია'];
        }
        if (! $phoneId || ! $token) {
            return ['success' => false, 'message' => 'WhatsApp credentials საჭიროა escalation test-ისთვის'];
        }
        try {
            $resp = Http::timeout(15)
                ->withToken($token)
                ->post("https://graph.facebook.com/v20.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => '🧪 Gadget AI · escalation test · '.now()->format('H:i')],
                ]);
            if ($resp->successful()) {
                return ['success' => true, 'message' => 'Test message გავიდა '.$to.'-ზე'];
            }
            return ['success' => false, 'message' => 'WhatsApp send უარყო ('.$resp->status().')', 'detail' => $resp->json('error') ?? $resp->json()];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'შეცდომა: '.$e->getMessage()];
        }
    }
}
