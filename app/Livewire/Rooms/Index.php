<?php

namespace App\Livewire\Rooms;

use App\Models\Property;
use App\Models\Room;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Rooms')]
#[Layout('layouts.app')]
class Index extends Component
{
    public function updateStatus(int $roomId, string $status): void
    {
        if (! in_array($status, Room::STATUSES, true)) {
            return;
        }
        $room = Room::findOrFail($roomId);
        // Don't let a manual click free a room that a reservation thinks it owns.
        if ($room->status === Room::STATUS_OCCUPIED && $status !== Room::STATUS_OCCUPIED) {
            $this->dispatch('toast',
                tone: 'warn',
                message: "Room {$room->number} is occupied — check the guest out before changing status.",
            );
            return;
        }
        $room->update(['status' => $status]);
        $this->dispatch('toast',
            tone: 'ok',
            message: "Room {$room->number} → {$status}",
        );
    }

    public function render()
    {
        $property = Property::query()->first();

        $rooms = Room::query()
            ->where('property_id', $property?->id)
            ->with('roomType')
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        return view('livewire.rooms.index', compact('rooms'));
    }
}
