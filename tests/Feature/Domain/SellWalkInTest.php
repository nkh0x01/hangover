<?php

use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\SellWalkIn;
use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Property;

beforeEach(function () {
    $this->property = Property::factory()->create(['base_currency' => 'USD']);
    $this->reception = InventoryLocation::factory()->reception()->create([
        'property_id' => $this->property->id,
    ]);
    $this->cola = Product::factory()->create([
        'property_id' => $this->property->id,
        'name' => 'Coca-Cola 330ml', 'sale_price' => 4.00,
    ]);
    $this->snickers = Product::factory()->create([
        'property_id' => $this->property->id,
        'name' => 'Snickers', 'sale_price' => 3.50,
    ]);
    app(ReceiveStock::class)->execute($this->cola, $this->reception, 20);
    app(ReceiveStock::class)->execute($this->snickers, $this->reception, 10);
});

it('a POS walk-in sale records payment, invoice and movements', function () {
    $service = app(InventoryService::class);
    $colaBefore = $service->stockAt($this->cola, $this->reception);
    $snickBefore = $service->stockAt($this->snickers, $this->reception);

    $invoice = app(SellWalkIn::class)->execute(
        $this->property,
        $this->reception,
        [
            ['product_id' => $this->cola->id,     'quantity' => 2],
            ['product_id' => $this->snickers->id, 'quantity' => 1],
        ],
        Payment::METHOD_CASH,
    );

    // Stock decreased
    expect($service->stockAt($this->cola, $this->reception))->toBe($colaBefore - 2)
        ->and($service->stockAt($this->snickers, $this->reception))->toBe($snickBefore - 1);

    // Invoice is a POS invoice with no reservation
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->source)->toBe(Invoice::SOURCE_POS)
        ->and($invoice->reservation_id)->toBeNull()
        ->and($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and((float) $invoice->total)->toBe(2 * 4.00 + 1 * 3.50)
        ->and($invoice->lines)->toHaveCount(2);

    // Matching POS payment exists
    $payment = Payment::query()
        ->where('property_id', $this->property->id)
        ->where('source', Payment::SOURCE_POS)
        ->orderByDesc('id')
        ->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(2 * 4.00 + 1 * 3.50)
        ->and($payment->reservation_id)->toBeNull();

    // Two sale movements (one per cart line) referencing the payment
    $movements = InventoryMovement::query()
        ->where('payment_id', $payment->id)
        ->where('type', InventoryMovement::TYPE_SALE)
        ->get();
    expect($movements)->toHaveCount(2);
});

it('POS sale fails atomically when stock is insufficient', function () {
    $colaBefore = app(InventoryService::class)->stockAt($this->cola, $this->reception);
    $invoicesBefore = Invoice::count();
    $paymentsBefore = Payment::count();

    expect(fn () => app(SellWalkIn::class)->execute(
        $this->property,
        $this->reception,
        [
            ['product_id' => $this->cola->id,     'quantity' => 2],
            ['product_id' => $this->snickers->id, 'quantity' => 999], // not enough
        ],
        Payment::METHOD_CASH,
    ))->toThrow(\App\Domain\Exceptions\InsufficientStock::class);

    // Cola stock unchanged because the transaction rolled back.
    expect(app(InventoryService::class)->stockAt($this->cola, $this->reception))->toBe($colaBefore)
        ->and(Invoice::count())->toBe($invoicesBefore)
        ->and(Payment::count())->toBe($paymentsBefore);
});
