<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

final class AdminSettingsPage extends Page
{
    protected static ?string $navigationGroup = 'პარამეტრები';

    protected static ?string $navigationLabel = 'პარამეტრები';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings';

    protected static string $view = 'filament.pages.admin-settings';

    protected static ?string $title = 'პარამეტრები';

    protected static ?int $navigationSort = 80;

    /**
     * @return list<array{label: string, value: string, ok: bool, help: string}>
     */
    public function settings(): array
    {
        return [
            [
                'label' => 'Pilot mode',
                'value' => config('pilot.enabled') ? 'enabled' : 'disabled',
                'ok' => (bool) config('pilot.enabled'),
                'help' => 'პილოტის რეჟიმი განსაზღვრავს პილოტური ოპერაციების ჩართვას.',
            ],
            [
                'label' => 'SMS provider',
                'value' => (string) config('sms.driver', 'unknown'),
                'ok' => config('sms.driver') === 'sender_ge',
                'help' => 'OTP SMS-ისთვის production-ზე მოსალოდნელია sender_ge.',
            ],
            [
                'label' => 'Broadcast',
                'value' => (string) config('broadcasting.default'),
                'ok' => filled(config('broadcasting.connections.reverb.key')),
                'help' => 'Realtime არხებისთვის Reverb key უნდა იყოს შევსებული.',
            ],
            [
                'label' => 'Queue',
                'value' => (string) config('queue.default'),
                'ok' => config('queue.default') !== 'sync',
                'help' => 'Production-ზე queue worker უნდა მუშაობდეს async რეჟიმში.',
            ],
        ];
    }
}
