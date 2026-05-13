<?php

namespace App\Livewire\Channels\Booking;

use App\Domain\Channels\Services\ChannelSyncService;
use App\Models\ChannelConnection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Test connection')]
#[Layout('layouts.app')]
class Test extends Component
{
    public ChannelConnection $connection;

    public ?bool $lastOk = null;

    public ?string $lastMessage = null;

    public ?string $lastRanAt = null;

    public function mount(ChannelConnection $connection): void
    {
        abort_unless($connection->isBooking(), 404);
        $this->connection = $connection;
    }

    public function runTest(): void
    {
        $result = app(ChannelSyncService::class)->testConnection($this->connection);
        $this->lastOk = $result->ok;
        $this->lastMessage = $result->ok
            ? __('Connection healthy.').' '.($this->connection->isDryRun() ? __('(dry-run, no HTTP call made)') : '')
            : ($result->error ?? __('Connection unhealthy.'));
        $this->lastRanAt = now()->toDateTimeString();
        $this->connection = $this->connection->fresh();
    }

    public function render()
    {
        return view('livewire.channels.booking.test');
    }
}
