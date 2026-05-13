<?php

namespace App\Livewire\Channels;

use App\Domain\Channels\Services\ChannelConflictService;
use App\Models\ChannelReservation;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Channel conflicts')]
#[Layout('layouts.app')]
class Conflicts extends Component
{
    public function retry(int $id): void
    {
        $row = ChannelReservation::findOrFail($id);
        $row = app(ChannelConflictService::class)->retry($row);
        $this->dispatch(
            'toast',
            tone: $row->status === ChannelReservation::STATUS_PROCESSED ? 'ok' : 'err',
            message: $row->status === ChannelReservation::STATUS_PROCESSED
                ? __('Resolved — reservation created.')
                : __('Still in conflict.'),
        );
    }

    public function dismiss(int $id): void
    {
        $row = ChannelReservation::findOrFail($id);
        app(ChannelConflictService::class)->dismiss($row);
        $this->dispatch('toast', tone: 'ok', message: __('Conflict dismissed.'));
    }

    public function render()
    {
        $property = Property::query()->first();
        $rows = ChannelReservation::query()
            ->with('connection')
            ->whereHas('connection', fn ($q) => $q->where('property_id', $property?->id))
            ->whereIn('status', [
                ChannelReservation::STATUS_CONFLICT,
                ChannelReservation::STATUS_FAILED,
                ChannelReservation::STATUS_DUPLICATE,
            ])
            ->orderByDesc('received_at')
            ->get();

        return view('livewire.channels.conflicts', compact('rows'));
    }
}
