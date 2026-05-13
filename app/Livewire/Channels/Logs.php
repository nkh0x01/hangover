<?php

namespace App\Livewire\Channels;

use App\Models\ChannelConnection;
use App\Models\ChannelSyncLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Sync log')]
#[Layout('layouts.app')]
class Logs extends Component
{
    use WithPagination;

    public ChannelConnection $connection;

    public string $filterStatus = '';

    public string $filterAction = '';

    public function mount(ChannelConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function updating($name): void
    {
        if (str_starts_with($name, 'filter')) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $logs = ChannelSyncLog::query()
            ->where('channel_connection_id', $this->connection->id)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterAction, fn ($q) => $q->where('action', $this->filterAction))
            ->orderByDesc('started_at')
            ->paginate(20);

        $actions = [
            ChannelSyncLog::ACTION_PULL_RESERVATIONS,
            ChannelSyncLog::ACTION_PUSH_AVAILABILITY,
            ChannelSyncLog::ACTION_PUSH_RATES,
            ChannelSyncLog::ACTION_PUSH_RESTRICTIONS,
            ChannelSyncLog::ACTION_TEST_CONNECTION,
            ChannelSyncLog::ACTION_WEBHOOK_RECEIVED,
        ];
        $statuses = [
            ChannelSyncLog::STATUS_SUCCESS,
            ChannelSyncLog::STATUS_PARTIAL,
            ChannelSyncLog::STATUS_FAILED,
        ];

        return view('livewire.channels.logs', compact('logs', 'actions', 'statuses'));
    }
}
