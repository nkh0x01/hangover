<?php

namespace App\Livewire\Channels;

use App\Domain\Availability\Period;
use App\Domain\Channels\Services\ChannelSyncService;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelSyncLog;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Channel')]
#[Layout('layouts.app')]
class Show extends Component
{
    public ChannelConnection $connection;

    public string $windowFrom;

    public string $windowTo;

    public function mount(ChannelConnection $connection): void
    {
        $this->connection = $connection;
        $today = CarbonImmutable::today();
        $this->windowFrom = $today->toDateString();
        $this->windowTo   = $today->addDays(30)->toDateString();
    }

    private function window(): Period
    {
        return new Period($this->windowFrom, $this->windowTo);
    }

    public function testConnection(): void
    {
        $result = app(ChannelSyncService::class)->testConnection($this->connection);
        $this->flash($result->ok, $result->ok ? __('Connection healthy.') : ($result->error ?? __('Unhealthy.')));
        $this->connection = $this->connection->fresh();
    }

    public function pullReservations(): void
    {
        $result = app(ChannelSyncService::class)->pullReservations($this->connection, $this->window());
        $this->flash($result->ok, $result->ok
            ? __(':n inbound row(s) processed.', ['n' => $result->processed])
            : ($result->error ?? __('Pull failed.')));
        $this->connection = $this->connection->fresh();
    }

    public function pushAvailability(): void
    {
        $result = app(ChannelSyncService::class)->pushAvailability($this->connection, $this->window());
        $this->flash($result->ok, $result->ok
            ? __('Availability pushed (:n rows).', ['n' => $result->processed])
            : ($result->error ?? __('Push failed.')));
        $this->connection = $this->connection->fresh();
    }

    public function pushRates(): void
    {
        $result = app(ChannelSyncService::class)->pushRates($this->connection, $this->window());
        $this->flash($result->ok, $result->ok
            ? __('Rates pushed (:n rows).', ['n' => $result->processed])
            : ($result->error ?? __('Push failed.')));
        $this->connection = $this->connection->fresh();
    }

    public function pushRestrictions(): void
    {
        $result = app(ChannelSyncService::class)->pushRestrictions($this->connection, $this->window());
        $this->flash($result->ok, $result->ok
            ? __('Restrictions pushed (:n rows).', ['n' => $result->processed])
            : ($result->error ?? __('Push failed.')));
        $this->connection = $this->connection->fresh();
    }

    private function flash(bool $ok, string $msg): void
    {
        $this->dispatch('toast', tone: $ok ? 'ok' : 'err', message: $msg);
    }

    public function render()
    {
        $recentLogs = ChannelSyncLog::query()
            ->where('channel_connection_id', $this->connection->id)
            ->orderByDesc('started_at')
            ->limit(8)
            ->get();

        $roomMappingsCount = $this->connection->roomMappings()->count();
        $rateMappingsCount = $this->connection->rateMappings()->count();
        $inboxCount = $this->connection->channelReservations()->count();
        $conflictCount = $this->connection->channelReservations()
            ->where('status', ChannelReservation::STATUS_CONFLICT)
            ->count();

        return view('livewire.channels.show', compact(
            'recentLogs', 'roomMappingsCount', 'rateMappingsCount', 'inboxCount', 'conflictCount',
        ));
    }
}
