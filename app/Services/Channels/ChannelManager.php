<?php

namespace App\Services\Channels;

use App\Services\Channels\Contracts\ChannelDriver;
use App\Services\SettingsService;
use InvalidArgumentException;

class ChannelManager
{
    /**
     * Mapping of {platform → {driver config key → env / settings key}}.
     * The values are the keys SettingsService and .env both use.
     * For each platform we resolve every value through SettingsService —
     * DB row first, .env fallback — so admin-saved values take effect
     * immediately without a redeploy.
     */
    private const KEY_MAP = [
        'whatsapp' => [
            'verify_token' => 'WHATSAPP_VERIFY_TOKEN',
            'app_secret' => 'WHATSAPP_APP_SECRET',
            'phone_number_id' => 'WHATSAPP_PHONE_NUMBER_ID',
            'business_account_id' => 'WHATSAPP_BUSINESS_ACCOUNT_ID',
            'access_token' => 'WHATSAPP_ACCESS_TOKEN',
        ],
        'messenger' => [
            'verify_token' => 'MESSENGER_VERIFY_TOKEN',
            'app_secret' => 'MESSENGER_APP_SECRET',
            'page_id' => 'MESSENGER_PAGE_ID',
            'page_access_token' => 'MESSENGER_PAGE_ACCESS_TOKEN',
        ],
        'instagram' => [
            'verify_token' => 'INSTAGRAM_VERIFY_TOKEN',
            'app_secret' => 'INSTAGRAM_APP_SECRET',
            'ig_account_id' => 'INSTAGRAM_ACCOUNT_ID',
            'access_token' => 'INSTAGRAM_ACCESS_TOKEN',
        ],
        'facebook' => [
            'page_id' => 'FACEBOOK_PAGE_ID',
            'page_access_token' => 'FACEBOOK_PAGE_ACCESS_TOKEN',
        ],
    ];

    /** @var array<string, ChannelDriver> */
    private array $drivers = [];

    public function __construct(private SettingsService $settings) {}

    public function driver(string $platform): ChannelDriver
    {
        if (isset($this->drivers[$platform])) {
            return $this->drivers[$platform];
        }

        $config = config("channels.$platform");
        if (! $config) {
            throw new InvalidArgumentException("Unknown channel: $platform");
        }

        // Overlay: every value comes from SettingsService (DB → .env fallback).
        // This is how Admin-panel saves take effect without a redeploy.
        foreach (self::KEY_MAP[$platform] ?? [] as $configKey => $settingKey) {
            $resolved = $this->settings->get($settingKey);
            if ($resolved !== null && $resolved !== '') {
                $config[$configKey] = $resolved;
            }
        }

        $class = $config['driver'];

        return $this->drivers[$platform] = new $class($config);
    }

    public function platforms(): array
    {
        return array_keys(config('channels'));
    }
}
