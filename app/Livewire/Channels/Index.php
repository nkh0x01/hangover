<?php

namespace App\Livewire\Channels;

use App\Models\ChannelConnection;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Channels')]
#[Layout('layouts.app')]
class Index extends Component
{
    public function render()
    {
        $property = Property::query()->first();
        $connections = ChannelConnection::query()
            ->where('property_id', $property?->id)
            ->withCount(['roomMappings', 'rateMappings', 'channelReservations'])
            ->orderBy('name')
            ->get();

        $conflicts = \App\Models\ChannelReservation::query()
            ->whereIn('channel_connection_id', $connections->pluck('id'))
            ->where('status', \App\Models\ChannelReservation::STATUS_CONFLICT)
            ->count();

        return view('livewire.channels.index', compact('connections', 'conflicts'));
    }
}
