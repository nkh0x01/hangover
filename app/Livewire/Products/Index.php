<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Property;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Products')]
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;
    public ?int $categoryId = null;
    public string $name = '';
    public string $sku = '';
    public string $barcode = '';
    public float $costPrice = 0;
    public float $salePrice = 0;
    public int $lowStockThreshold = 5;
    public bool $trackStock = true;
    public bool $active = true;
    public ?string $error = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'categoryId', 'name', 'sku', 'barcode', 'costPrice', 'salePrice', 'lowStockThreshold', 'trackStock', 'active', 'error']);
        $this->trackStock = true;
        $this->active = true;
        $this->lowStockThreshold = 5;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $p = Product::findOrFail($id);
        $this->editingId = $p->id;
        $this->categoryId = $p->category_id;
        $this->name = $p->name;
        $this->sku = (string) $p->sku;
        $this->barcode = (string) $p->barcode;
        $this->costPrice = (float) $p->cost_price;
        $this->salePrice = (float) $p->sale_price;
        $this->lowStockThreshold = (int) $p->low_stock_threshold;
        $this->trackStock = (bool) $p->track_stock;
        $this->active = (bool) $p->active;
        $this->error = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'      => 'required|string|max:120',
            'sku'       => 'nullable|string|max:60',
            'salePrice' => 'required|numeric|min:0',
            'costPrice' => 'required|numeric|min:0',
            'lowStockThreshold' => 'required|integer|min:0',
        ]);

        $property = Property::query()->first();
        if (! $property) {
            $this->error = 'No property configured.';
            return;
        }

        $data = [
            'property_id' => $property->id,
            'category_id' => $this->categoryId,
            'name'        => $this->name,
            'sku'         => $this->sku ?: null,
            'barcode'     => $this->barcode ?: null,
            'cost_price'  => $this->costPrice,
            'sale_price'  => $this->salePrice,
            'low_stock_threshold' => $this->lowStockThreshold,
            'track_stock' => $this->trackStock,
            'active'      => $this->active,
        ];

        if ($this->editingId) {
            Product::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', tone: 'ok', message: __('Product updated.'));
        } else {
            Product::create($data);
            $this->dispatch('toast', tone: 'ok', message: __('Product created.'));
        }

        $this->showForm = false;
    }

    public function render()
    {
        $property = Property::query()->first();

        $products = Product::query()
            ->where('property_id', $property?->id)
            ->with('category')
            ->withSum('stocks as total_stock', 'quantity')
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('sku', 'like', $term)
                      ->orWhere('barcode', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(20);

        $categories = ProductCategory::query()
            ->where('property_id', $property?->id)
            ->orderBy('name')
            ->get();

        return view('livewire.products.index', compact('products', 'categories'));
    }
}
