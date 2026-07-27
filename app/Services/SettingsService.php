<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public const SECRET_KEYS = [
        'WHATSAPP_ACCESS_TOKEN',
        'WHATSAPP_APP_SECRET',
        'WHATSAPP_VERIFY_TOKEN',
        'MESSENGER_PAGE_ACCESS_TOKEN',
        'MESSENGER_APP_SECRET',
        'MESSENGER_VERIFY_TOKEN',
        'INSTAGRAM_ACCESS_TOKEN',
        'INSTAGRAM_APP_SECRET',
        'INSTAGRAM_VERIFY_TOKEN',
        'GADGET_WC_CONSUMER_SECRET',
        'GADGET_WC_WEBHOOK_SECRET',
        'PAYMENT_API_KEY',
        'PAYMENT_API_SECRET',
        'ANTHROPIC_API_KEY',
    ];

    public const GROUPS = [
        'auto_reply' => [
            'ROLLOUT_MODE',
            'AUTO_REPLY_ENABLED',
            'AUTO_REPLY_MESSENGER_ENABLED',
            'AUTO_REPLY_WHATSAPP_ENABLED',
            'AUTO_REPLY_INSTAGRAM_ENABLED',
            'AUTO_REPLY_DELAY_SECONDS',
            'AUTO_REPLY_MAX_PER_HOUR',
            'AUTO_REPLY_BUSINESS_HOURS_ONLY',
            'AUTO_REPLY_BUSINESS_HOURS_START',
            'AUTO_REPLY_BUSINESS_HOURS_END',
        ],
        'whatsapp' => [
            'WHATSAPP_PHONE_NUMBER_ID',
            'WHATSAPP_BUSINESS_ACCOUNT_ID',
            'WHATSAPP_ACCESS_TOKEN',
            'WHATSAPP_APP_SECRET',
            'WHATSAPP_VERIFY_TOKEN',
        ],
        'messenger' => [
            'MESSENGER_PAGE_ID',
            'MESSENGER_PAGE_ACCESS_TOKEN',
            'MESSENGER_APP_SECRET',
            'MESSENGER_VERIFY_TOKEN',
        ],
        'instagram' => [
            'INSTAGRAM_ACCOUNT_ID',
            'INSTAGRAM_ACCESS_TOKEN',
            'INSTAGRAM_APP_SECRET',
            'INSTAGRAM_VERIFY_TOKEN',
        ],
        'woocommerce' => [
            'GADGET_WC_BASE_URL',
            'GADGET_WC_CONSUMER_KEY',
            'GADGET_WC_CONSUMER_SECRET',
            'GADGET_WC_WEBHOOK_SECRET',
        ],
        'payment' => [
            'PAYMENT_PROVIDER',
            'PAYMENT_API_KEY',
            'PAYMENT_API_SECRET',
            'PAYMENT_CALLBACK_URL',
        ],
        'ai' => [
            'ANTHROPIC_API_KEY',
            'ANTHROPIC_MODEL_PRIMARY',
            'ANTHROPIC_MODEL_LIGHT',
            'ANTHROPIC_MAX_TOKENS',
        ],
        'escalation' => [
            'ESCALATION_WHATSAPP_TO',
            'ESCALATION_ENABLED',
        ],
    ];

    /**
     * Get setting value. DB first, .env fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->first();
        if ($setting && $setting->getAttribute('value') !== null && $setting->getAttribute('value') !== '') {
            return $setting->value;
        }
        return env($key, $default);
    }

    /**
     * Save setting. Empty/null values are ignored (so masked "current value"
     * can be displayed without forcing a re-save).
     */
    public function set(string $key, ?string $value, ?string $group = null): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $isSecret = $this->isSecret($key);
        // Encryption MUST happen here, not via Eloquent mutator. During
        // updateOrCreate Eloquent fills attributes in array order, so the
        // value setter would fire before is_secret is on the model and the
        // "encrypt-if-secret" decision would always read is_secret=false.
        $storedValue = $isSecret ? Crypt::encryptString($value) : $value;

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'group' => $group ?? $this->groupFor($key) ?? 'general',
                'is_secret' => $isSecret,
            ],
        );
    }

    public function has(string $key): bool
    {
        $v = $this->get($key);
        return $v !== null && $v !== '';
    }

    /** Bool helper. Treats "1", "true", "yes", "on" as true (case-insensitive). */
    public function getBool(string $key, bool $default = false): bool
    {
        $v = $this->get($key);
        if ($v === null || $v === '') return $default;
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Int helper with default. */
    public function getInt(string $key, int $default = 0): int
    {
        $v = $this->get($key);
        if ($v === null || $v === '') return $default;
        return (int) $v;
    }

    /**
     * Master toggle + per-channel toggle for the auto-reply pipeline.
     * Both must be true for auto-reply to fire on a given platform.
     */
    public function isAutoReplyEnabledFor(string $platform): bool
    {
        // Safe Mode is an independent kill-switch. When ON, NO auto-reply
        // fires on any channel, but inbox keeps receiving and manual reply
        // keeps working. AUTO_REPLY_ENABLED is left untouched so flipping
        // Safe Mode OFF restores the previous auto-reply state exactly.
        if ($this->getBool('SAFE_MODE_ENABLED', false)) {
            return false;
        }
        if (! $this->getBool('AUTO_REPLY_ENABLED', false)) {
            return false;
        }
        $channelKey = match ($platform) {
            'whatsapp' => 'AUTO_REPLY_WHATSAPP_ENABLED',
            'messenger', 'facebook' => 'AUTO_REPLY_MESSENGER_ENABLED',
            'instagram' => 'AUTO_REPLY_INSTAGRAM_ENABLED',
            default => null,
        };
        if ($channelKey === null) return false;
        return $this->getBool($channelKey, false);
    }

    public const ROLLOUT_MODES = ['beta', 'public_receive_only', 'public_product_only', 'full_auto'];

    /**
     * Current public-rollout mode. Default: public_product_only — the safe
     * mode that only auto-replies to clear, WC-grounded product questions.
     *   beta                — auto-reply both product + general (internal testing)
     *   public_receive_only — never auto-send; receive + note for humans
     *   public_product_only — auto-send ONLY wc_grounded product replies (DEFAULT)
     *   full_auto           — autonomous sales engine: tool-use loop (product
     *                          search, native cards, draft orders, BOG payment
     *                          links, escalation). Opt-in; all safety gates apply.
     */
    public function rolloutMode(): string
    {
        $mode = (string) ($this->get('ROLLOUT_MODE') ?: 'public_product_only');
        if (! in_array($mode, self::ROLLOUT_MODES, true)) {
            $mode = 'public_product_only';
        }
        // full_auto → GenerateAIReply hands off to the autonomous sales engine
        // (ReplyEngine tool-use loop). Opt-in only: default stays product-only.
        return $mode;
    }

    public function isWithinBusinessHours(?\DateTimeInterface $now = null): bool
    {
        if (! $this->getBool('AUTO_REPLY_BUSINESS_HOURS_ONLY', false)) {
            return true; // gate disabled → always within
        }
        $start = $this->getInt('AUTO_REPLY_BUSINESS_HOURS_START', 10);
        $end = $this->getInt('AUTO_REPLY_BUSINESS_HOURS_END', 22);
        $hour = ($now ?? new \DateTime('now', new \DateTimeZone(config('app.timezone'))))->format('G');
        $hour = (int) $hour;
        if ($start <= $end) {
            return $hour >= $start && $hour < $end;
        }
        // wraps midnight (e.g. 22 → 6)
        return $hour >= $start || $hour < $end;
    }

    /**
     * Explicitly remove a saved value. After this, get() will fall back to
     * the .env value (if any).
     */
    public function forget(string $key): bool
    {
        return Setting::where('key', $key)->delete() > 0;
    }

    public function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    public function groupFor(string $key): ?string
    {
        foreach (self::GROUPS as $group => $keys) {
            if (in_array($key, $keys, true)) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Group payload for the UI: each key returns
     *   value:        raw plaintext (only for non-secrets)
     *   masked:       masked string (e.g. "sk-a••••wxyz") if secret
     *   is_secret:    bool
     *   is_set:       whether a value exists (DB or env)
     *   source:       'db' | 'env' | 'none'
     */
    public function groupPayload(string $group): array
    {
        $keys = self::GROUPS[$group] ?? [];
        $out = [];
        $dbBag = Setting::where('group', $group)->get()->keyBy('key');
        foreach ($keys as $k) {
            $setting = $dbBag->get($k);
            $isSecret = $this->isSecret($k);
            $source = 'none';
            $value = null;
            if ($setting && $setting->getAttribute('value') !== null && $setting->getAttribute('value') !== '') {
                $value = $setting->value;
                $source = 'db';
            } elseif (env($k) !== null && env($k) !== '') {
                $value = env($k);
                $source = 'env';
            }

            $out[$k] = [
                'is_secret' => $isSecret,
                'is_set' => $value !== null && $value !== '',
                'source' => $source,
                'masked' => $this->maskValue($value, $isSecret),
                'value' => $isSecret ? null : $value, // never return secrets in plain
            ];
        }
        return $out;
    }

    private function maskValue(?string $v, bool $isSecret): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (! $isSecret) {
            return $v;
        }
        $len = strlen($v);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($v, 0, 4).str_repeat('•', max(0, $len - 8)).substr($v, -4);
    }
}
