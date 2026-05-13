<?php

namespace App\Livewire\Channels\Booking;

use App\Domain\Availability\Period;
use App\Domain\Channels\Services\ChannelSyncService;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelSyncLog;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Booking.com connection')]
#[Layout('layouts.app')]
class Show extends Component
{
    public ChannelConnection $connection;

    public string $windowFrom;

    public string $windowTo;

    public ?string $pendingLiveAction = null;

    public function mount(ChannelConnection $connection): void
    {
        abort_unless($connection->isBooking(), 404);
        $this->connection = $connection;
        $this->windowFrom = CarbonImmutable::today()->toDateString();
        $this->windowTo   = CarbonImmutable::today()->addDays(30)->toDateString();
    }

    public function toggleDryRun(): void
    {
        $this->connection->update([
            'dry_run' => ! $this->connection->dry_run,
            // Going live without confirmed credentials is the operator's call —
            // we don't change status here.
            'live_confirmed_at' => null,
        ]);
        $this->connection = $this->connection->fresh();
        $this->dispatch(
            'toast',
            tone: 'ok',
            message: $this->connection->isDryRun() ? __('Switched to dry-run mode.') : __('Switched to LIVE mode.'),
        );
    }

    /**
     * For dry-run pushes we just go ahead. For live pushes we open a confirm
     * modal first — the operator has to click "Confirm" which sets
     * live_confirmed_at and then re-issues the action.
     */
    public function requestPush(string $action): void
    {
        if ($this->connection->isDryRun()) {
            $this->execute($action);
            return;
        }
        $this->pendingLiveAction = $action;
    }

    public function cancelLivePush(): void
    {
        $this->pendingLiveAction = null;
    }

    public function confirmLivePush(): void
    {
        if (! $this->pendingLiveAction) {
            return;
        }
        // Mark the connection as having a fresh user confirmation; the
        // provider's guardLivePush() consults this and rejects pushes whose
        // confirmation is older than 60 seconds.
        $this->connection->update(['live_confirmed_at' => now()]);
        $action = $this->pendingLiveAction;
        $this->pendingLiveAction = null;
        $this->execute($action);
        // Clear the confirmation so the next live push needs its own.
        $this->connection->update(['live_confirmed_at' => null]);
    }

    private function execute(string $action): void
    {
        $svc = app(ChannelSyncService::class);
        $window = new Period($this->windowFrom, $this->windowTo);

        $result = match ($action) {
            'test' => $svc->testConnection($this->connection),
            'pull' => $svc->pullReservations($this->connection, $window),
            'push_availability'  => $svc->pushAvailability($this->connection, $window),
            'push_rates'         => $svc->pushRates($this->connection, $window),
            'push_restrictions'  => $svc->pushRestrictions($this->connection, $window),
            default => null,
        };

        $this->connection = $this->connection->fresh();
        if (! $result) {
            $this->dispatch('toast', tone: 'err', message: __('Unknown action.'));
            return;
        }
        if ($result->ok) {
            $this->dispatch('toast', tone: 'ok', message: __(':n row(s) processed.', ['n' => $result->processed]));
        } else {
            $this->dispatch('toast', tone: 'err', message: $result->error ?? __('Sync failed.'));
        }
    }

    public function render()
    {
        $recentLogs = ChannelSyncLog::query()
            ->where('channel_connection_id', $this->connection->id)
            ->orderByDesc('started_at')
            ->limit(8)
            ->get();

        $conflictCount = ChannelReservation::query()
            ->where('channel_connection_id', $this->connection->id)
            ->where('status', ChannelReservation::STATUS_CONFLICT)
            ->count();

        return view('livewire.channels.booking.show', compact('recentLogs', 'conflictCount'));
    }
}
