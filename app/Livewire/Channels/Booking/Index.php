<?php

namespace App\Livewire\Channels\Booking;

use App\Models\ChannelConnection;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Booking.com')]
#[Layout('layouts.app')]
class Index extends Component
{
    public string $newName = '';

    public function createConnection(): void
    {
        $property = Property::query()->firstOrFail();
        $this->validate([
            'newName' => 'required|string|max:120',
        ]);
        // Always create in DRY-RUN + SANDBOX mode. The operator has to walk
        // through the credentials and live-mode flows explicitly.
        ChannelConnection::create([
            'property_id' => $property->id,
            'channel' => ChannelConnection::CHANNEL_BOOKING,
            'name' => $this->newName,
            'status' => ChannelConnection::STATUS_PAUSED,
            'dry_run' => true,
            'settings' => ['sandbox' => true, 'currency' => $property->base_currency],
            'credentials' => null,
        ]);

        $this->newName = '';
        $this->dispatch('toast', tone: 'ok', message: __('Booking.com connection created (dry-run, paused).'));
    }

    public function render()
    {
        $property = Property::query()->first();
        $connections = ChannelConnection::query()
            ->where('property_id', $property?->id)
            ->where('channel', ChannelConnection::CHANNEL_BOOKING)
            ->orderBy('name')
            ->get();

        return view('livewire.channels.booking.index', compact('connections'));
    }
}
