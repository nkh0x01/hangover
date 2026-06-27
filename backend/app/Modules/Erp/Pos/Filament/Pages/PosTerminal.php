<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Filament\Pages;

use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Pos\Models\PosShift;
use App\Modules\Erp\Pos\Services\PosSaleService;
use App\Modules\Erp\Pos\Services\PosShiftService;
use App\Modules\Erp\Pricing\Models\PriceList;
use App\Modules\Erp\Pricing\Services\PriceResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Single-window cashier panel: scan, cart, payment, shift X/Z — all from one
 * screen without switching context. UI lives in the Filament admin (the
 * platform's Livewire surface); all money logic is delegated to the tested
 * POS services so this class only orchestrates screen state.
 */
final class PosTerminal extends Page
{
    protected static ?string $navigationGroup = 'POS';

    protected static ?string $navigationLabel = 'მოლარის პანელი';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.erp-pos-terminal';

    protected static ?string $title = 'მოლარის პანელი';

    protected static ?string $slug = 'pos-terminal';

    public ?int $shiftId = null;

    public string $barcode = '';

    public float $openingCash = 0.0;

    public float $countedCash = 0.0;

    /** @var list<array{variant_id:int, label:string, qty:int, unit_price:float}> */
    public array $cart = [];

    public function mount(): void
    {
        $this->shiftId = $this->activeShift()?->id;
    }

    public function activeShift(): ?PosShift
    {
        $branchId = (int) (auth()->user()->branch_id ?? 0);

        return PosShift::query()
            ->where('branch_id', $branchId)
            ->where('user_id', auth()->id())
            ->where('status', PosShift::STATUS_OPEN)
            ->latest('id')
            ->first();
    }

    public function openShift(): void
    {
        $shift = app(PosShiftService::class)->open(
            (int) (auth()->user()->branch_id ?? 0),
            (int) auth()->id(),
            $this->openingCash,
        );

        $this->shiftId = $shift->id;
        $this->openingCash = 0.0;
        Notification::make()->title('ცვლა გაიხსნა')->success()->send();
    }

    public function scan(): void
    {
        $code = trim($this->barcode);
        $this->barcode = '';

        if ($code === '') {
            return;
        }

        $variant = ProductVariant::query()->where('barcode', $code)->first();

        if ($variant === null) {
            Notification::make()->title('SKU ვერ მოიძებნა')->danger()->send();

            return;
        }

        $this->addVariant($variant);
    }

    public function addVariant(ProductVariant $variant): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['variant_id'] === $variant->id) {
                $this->cart[$i]['qty']++;

                return;
            }
        }

        $branchId = (int) (auth()->user()->branch_id ?? 0);
        $item = app(PriceResolver::class)->resolve((int) $variant->id, null, $branchId, PriceList::TYPE_RETAIL);

        $this->cart[] = [
            'variant_id' => (int) $variant->id,
            'label' => $variant->variant_sku,
            'qty' => 1,
            'unit_price' => $item !== null ? (float) $item->price : 0.0,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function cartTotal(): float
    {
        return round(array_sum(array_map(
            fn (array $line): float => $line['unit_price'] * $line['qty'],
            $this->cart,
        )), 2);
    }

    public function pay(string $method = 'cash'): void
    {
        $shift = $this->shiftId !== null ? PosShift::find($this->shiftId) : null;

        if ($shift === null || ! $shift->isOpen() || $this->cart === []) {
            Notification::make()->title('გახსენით ცვლა და დაამატეთ პროდუქტი')->warning()->send();

            return;
        }

        $lines = array_map(fn (array $line): array => [
            'variant_id' => $line['variant_id'],
            'qty' => $line['qty'],
            'unit_price' => $line['unit_price'],
        ], $this->cart);

        $total = $this->cartTotal();

        app(PosSaleService::class)->register($shift, $lines, [
            ['method' => $method, 'amount' => $total],
        ]);

        $this->cart = [];
        Notification::make()->title('გაყიდვა დასრულდა')->success()->send();
    }

    public function closeShift(): void
    {
        $shift = $this->shiftId !== null ? PosShift::find($this->shiftId) : null;

        if ($shift === null) {
            return;
        }

        app(PosShiftService::class)->close($shift, $this->countedCash);
        $this->shiftId = null;
        $this->countedCash = 0.0;
        Notification::make()->title('ცვლა დაიხურა (Z report)')->success()->send();
    }
}
