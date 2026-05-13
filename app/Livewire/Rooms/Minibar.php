<?php

namespace App\Livewire\Rooms;

use App\Domain\Inventory\Actions\RefillMinibar;
use App\Domain\Inventory\InventoryService;
use App\Models\Product;
use App\Models\Room;
use App\Models\RoomMinibarItem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Minibar')]
#[Layout('layouts.app')]
class Minibar extends Component
{
    public Room $room;

    public ?int $newProductId = null;
    public int $newParLevel = 1;

    public function mount(Room $room): void
    {
        $this->room = $room->load(['roomType', 'minibarItems.product', 'minibarLocation']);
    }

    public function updatePar(int $itemId, int $par): void
    {
        if ($par < 0) {
            return;
        }
        RoomMinibarItem::query()
            ->where('id', $itemId)
            ->where('room_id', $this->room->id)
            ->update(['par_level' => $par]);
        $this->dispatch('toast', tone: 'ok', message: __('Par level saved.'));
    }

    public function addItem(): void
    {
        $this->validate([
            'newProductId' => 'required|integer|exists:products,id',
            'newParLevel'  => 'required|integer|min:0|max:99',
        ]);

        RoomMinibarItem::firstOrCreate(
            ['room_id' => $this->room->id, 'product_id' => $this->newProductId],
            ['par_level' => $this->newParLevel],
        );
        $this->reset(['newProductId', 'newParLevel']);
        $this->newParLevel = 1;
        $this->dispatch('toast', tone: 'ok', message: __('Product added to minibar.'));
    }

    public function removeItem(int $itemId): void
    {
        RoomMinibarItem::query()
            ->where('id', $itemId)
            ->where('room_id', $this->room->id)
            ->delete();
        $this->dispatch('toast', tone: 'warn', message: __('Product removed from minibar.'));
    }

    public function refill(): void
    {
        $result = app(RefillMinibar::class)->execute($this->room, auth()->user());
        $refilled = count($result['refilled']);
        $skipped = count($result['skipped']);
        if ($skipped > 0) {
            $this->dispatch('toast', tone: 'warn',
                message: __('Refill partially complete: :refilled refilled, :skipped skipped (low storage).', [
                    'refilled' => $refilled,
                    'skipped' => $skipped,
                ]),
            );
        } else {
            $this->dispatch('toast', tone: 'ok',
                message: __('Minibar refilled (:n products).', ['n' => $refilled]),
            );
        }
    }

    public function render()
    {
        $service = app(InventoryService::class);

        $this->room->load(['minibarItems.product', 'minibarLocation.stocks']);

        $byProduct = $this->room->minibarLocation?->stocks->keyBy('product_id') ?? collect();

        $items = $this->room->minibarItems->map(function ($item) use ($byProduct) {
            $item->current = (int) ($byProduct->get($item->product_id)->quantity ?? 0);
            return $item;
        });

        $availableProducts = Product::query()
            ->where('property_id', $this->room->property_id)
            ->where('active', true)
            ->whereNotIn('id', $items->pluck('product_id'))
            ->orderBy('name')
            ->get();

        return view('livewire.rooms.minibar', compact('items', 'availableProducts'));
    }
}
