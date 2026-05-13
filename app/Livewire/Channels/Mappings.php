<?php

namespace App\Livewire\Channels;

use App\Domain\Channels\Services\ChannelMappingService;
use App\Models\ChannelConnection;
use App\Models\RoomType;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Channel mappings')]
#[Layout('layouts.app')]
class Mappings extends Component
{
    public ChannelConnection $connection;

    public string $newRoomExternalId = '';

    public ?int $newRoomTypeId = null;

    public string $newRoomExternalName = '';

    public string $newRateExternalId = '';

    public ?int $newRateRoomTypeId = null;

    public string $newRateExternalName = '';

    public ?float $newRateMarkupPercent = null;

    public function mount(ChannelConnection $connection): void
    {
        $this->connection = $connection;
    }

    public function addRoomMapping(): void
    {
        $this->validate([
            'newRoomExternalId' => 'required|string|max:191',
            'newRoomTypeId' => 'required|integer|exists:room_types,id',
        ]);

        $roomType = RoomType::findOrFail($this->newRoomTypeId);
        app(ChannelMappingService::class)->mapRoom(
            $this->connection,
            $roomType,
            $this->newRoomExternalId,
            $this->newRoomExternalName ?: null,
        );
        $this->dispatch('toast', tone: 'ok', message: __('Room mapping saved.'));
        $this->reset(['newRoomExternalId', 'newRoomExternalName', 'newRoomTypeId']);
    }

    public function deleteRoomMapping(int $id): void
    {
        $row = $this->connection->roomMappings()->findOrFail($id);
        $row->delete();
        $this->dispatch('toast', tone: 'ok', message: __('Room mapping removed.'));
    }

    public function addRateMapping(): void
    {
        $this->validate([
            'newRateExternalId' => 'required|string|max:191',
            'newRateRoomTypeId' => 'required|integer|exists:room_types,id',
        ]);

        $roomType = RoomType::findOrFail($this->newRateRoomTypeId);
        app(ChannelMappingService::class)->mapRate(
            $this->connection,
            $roomType,
            $this->newRateExternalId,
            $this->newRateExternalName ?: null,
            $this->newRateMarkupPercent,
            null,
        );
        $this->dispatch('toast', tone: 'ok', message: __('Rate mapping saved.'));
        $this->reset(['newRateExternalId', 'newRateExternalName', 'newRateRoomTypeId', 'newRateMarkupPercent']);
    }

    public function deleteRateMapping(int $id): void
    {
        $row = $this->connection->rateMappings()->findOrFail($id);
        $row->delete();
        $this->dispatch('toast', tone: 'ok', message: __('Rate mapping removed.'));
    }

    public function render()
    {
        $roomTypes = $this->connection->property()->first()?->roomTypes()->orderBy('name')->get() ?? collect();
        $rooms = $this->connection->roomMappings()->with('roomType')->orderBy('id')->get();
        $rates = $this->connection->rateMappings()->with('roomType')->orderBy('id')->get();

        return view('livewire.channels.mappings', compact('roomTypes', 'rooms', 'rates'));
    }
}
