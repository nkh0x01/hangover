<?php

namespace App\Livewire\Channels\Booking;

use App\Models\ChannelConnection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Booking.com credentials')]
#[Layout('layouts.app')]
class Credentials extends Component
{
    public ChannelConnection $connection;

    public string $hotelId = '';

    public string $secret = '';

    public string $webhookSecret = '';

    public bool $sandbox = true;

    public function mount(ChannelConnection $connection): void
    {
        abort_unless($connection->isBooking(), 404);
        $this->connection = $connection;
        $creds = $connection->credentials ?? [];
        $this->hotelId = (string) ($creds['hotel_id'] ?? '');
        // Existing secrets are deliberately NOT echoed back to the form — the
        // operator must re-enter them to change them. We just signal presence.
        $this->webhookSecret = '';
        $this->secret = '';
        $this->sandbox = (bool) data_get($connection->settings, 'sandbox', true);
    }

    public function generateWebhookSecret(): void
    {
        $this->webhookSecret = Str::random(48);
    }

    public function save(): void
    {
        $this->validate([
            'hotelId' => 'required|string|max:60',
            'sandbox' => 'boolean',
        ]);

        $existing = $this->connection->credentials ?? [];
        $creds = [
            'hotel_id' => $this->hotelId,
            // If the operator left the secret blank, keep the previous value
            // (they're editing other fields).
            'secret' => $this->secret !== '' ? $this->secret : ($existing['secret'] ?? null),
            'webhook_secret' => $this->webhookSecret !== ''
                ? $this->webhookSecret
                : ($existing['webhook_secret'] ?? null),
        ];

        $settings = array_merge($this->connection->settings ?? [], [
            'sandbox' => $this->sandbox,
        ]);

        $this->connection->update([
            'credentials' => $creds,
            'settings' => $settings,
        ]);
        $this->dispatch('toast', tone: 'ok', message: __('Credentials saved (encrypted).'));
        $this->secret = '';
        $this->webhookSecret = '';
        $this->connection = $this->connection->fresh();
    }

    public function render()
    {
        $creds = $this->connection->credentials ?? [];
        $hasSecret = ! empty($creds['secret']);
        $hasWebhookSecret = ! empty($creds['webhook_secret']);
        $webhookUrl = route('webhooks.booking', $this->connection);

        return view('livewire.channels.booking.credentials', compact('hasSecret', 'hasWebhookSecret', 'webhookUrl'));
    }
}
