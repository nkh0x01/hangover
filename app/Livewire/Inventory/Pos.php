<?php

namespace App\Livewire\Inventory;

use App\Domain\Inventory\Actions\SellWalkIn;
use App\Domain\Inventory\InventoryService;
use App\Domain\Exceptions\InsufficientStock;
use App\Models\InventoryLocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reception POS-lite. Search → add to cart → choose payment method → sell.
 * Produces a Payment + Invoice + N inventory movements via SellWalkIn.
 */
#[Title('POS')]
#[Layout('layouts.app')]
class Pos extends Component
{
    public string $search = '';
    public ?int $locationId = null;
    public string $paymentMethod = Payment::METHOD_CASH;

    /** @var array<int, array{product_id: int, quantity: int}> */
    public array $cart = [];

    public ?string $lastInvoiceNumber = null;
    public ?int $lastInvoiceId = null;

    public function mount(): void
    {
        $property = Property::query()->first();
        // Default to the reception location.
        $this->locationId = InventoryLocation::query()
            ->where('property_id', $property?->id)
            ->where('type', InventoryLocation::TYPE_RECEPTION)
            ->value('id');
    }

    public function addToCart(int $productId): void
    {
        $existing = collect($this->cart)->firstWhere('product_id', $productId);
        if ($existing) {
            $this->cart = collect($this->cart)->map(function ($row) use ($productId) {
                if ($row['product_id'] === $productId) {
                    $row['quantity']++;
                }
                return $row;
            })->all();
        } else {
            $this->cart[] = ['product_id' => $productId, 'quantity' => 1];
        }
    }

    public function setQty(int $productId, int $qty): void
    {
        if ($qty <= 0) {
            $this->cart = collect($this->cart)
                ->reject(fn ($row) => $row['product_id'] === $productId)
                ->values()
                ->all();
            return;
        }
        $this->cart = collect($this->cart)->map(function ($row) use ($productId, $qty) {
            if ($row['product_id'] === $productId) {
                $row['quantity'] = $qty;
            }
            return $row;
        })->all();
    }

    public function remove(int $productId): void
    {
        $this->cart = collect($this->cart)
            ->reject(fn ($row) => $row['product_id'] === $productId)
            ->values()
            ->all();
    }

    public function clear(): void
    {
        $this->cart = [];
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            $this->dispatch('toast', tone: 'warn', message: __('Cart is empty.'));
            return;
        }

        $property = Property::query()->first();
        $location = InventoryLocation::query()->findOrFail($this->locationId);

        try {
            $invoice = app(SellWalkIn::class)->execute(
                $property,
                $location,
                $this->cart,
                $this->paymentMethod,
                auth()->user(),
            );
            $this->lastInvoiceNumber = $invoice->number;
            $this->lastInvoiceId = $invoice->id;
            $this->cart = [];
            $this->dispatch('toast', tone: 'ok',
                message: __('Sale completed · invoice :number', ['number' => $invoice->number]),
            );
        } catch (InsufficientStock $e) {
            $this->dispatch('toast', tone: 'error', message: $e->getMessage());
        } catch (\Throwable $e) {
            $this->dispatch('toast', tone: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $property = Property::query()->first();

        $products = Product::query()
            ->where('property_id', $property?->id)
            ->where('active', true)
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('sku', 'like', $term)
                      ->orWhere('barcode', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit(48)
            ->get();

        $service = app(InventoryService::class);
        $location = $this->locationId
            ? InventoryLocation::find($this->locationId)
            : null;
        foreach ($products as $p) {
            $p->location_stock = $location ? $service->stockAt($p, $location) : 0;
        }

        $cartLines = collect($this->cart)->map(function ($row) use ($products) {
            $product = $products->firstWhere('id', $row['product_id'])
                ?? Product::find($row['product_id']);
            $unit = (float) $product?->sale_price;
            $line_total = round($unit * $row['quantity'], 2);
            return compact('product') + [
                'quantity' => $row['quantity'],
                'unit' => $unit,
                'line_total' => $line_total,
            ];
        })->values();

        $total = (float) $cartLines->sum('line_total');

        $locations = InventoryLocation::query()
            ->where('property_id', $property?->id)
            ->whereIn('type', [InventoryLocation::TYPE_RECEPTION, InventoryLocation::TYPE_STORAGE])
            ->orderBy('type')
            ->get();

        return view('livewire.inventory.pos', [
            'products' => $products,
            'locations' => $locations,
            'cartLines' => $cartLines,
            'total' => $total,
            'currency' => $property?->base_currency ?? '',
            'methods' => Payment::METHODS,
        ]);
    }
}
