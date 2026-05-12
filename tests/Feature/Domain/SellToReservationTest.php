<?php

use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\SellToReservation;
use App\Domain\Inventory\InventoryService;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ReservationCharge;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->product = Product::factory()->create([
        'property_id' => $this->p->property->id,
        'name' => 'Coca-Cola 330ml',
        'sale_price' => 4.00,
        'cost_price' => 1.50,
    ]);
    $this->reception = InventoryLocation::factory()->reception()->create([
        'property_id' => $this->p->property->id,
    ]);
    app(ReceiveStock::class)->execute($this->product, $this->reception, 20);
});

it('product can be sold to a reservation and adds a folio charge', function () {
    $reservation = $this->p->createReservation();
    $service = app(InventoryService::class);
    $before = $service->stockAt($this->product, $this->reception);

    $charge = app(SellToReservation::class)->execute(
        $reservation, $this->product, $this->reception, 2,
    );

    expect($charge)->toBeInstanceOf(ReservationCharge::class)
        ->and($charge->type)->toBe(ReservationCharge::TYPE_PRODUCT)
        ->and((float) $charge->total)->toBe(8.00)
        ->and($service->stockAt($this->product, $this->reception))->toBe($before - 2);

    $reservation->refresh();
    expect((float) $reservation->extras_total)->toBe(8.00)
        ->and((float) $reservation->grand_total)->toBe(
            (float) $reservation->room_rate_total + 8.00,
        );
});

it('check-out includes minibar/product charges in the folio and invoice', function () {
    $reservation = $this->p->createReservation();
    app(CheckInReservation::class)->execute($reservation);

    app(SellToReservation::class)->execute(
        $reservation->fresh(), $this->product, $this->reception, 3,
    );

    $invoice = app(CheckOutReservation::class)->execute($reservation->fresh());

    expect((float) $invoice->total)->toBe(
        (float) $reservation->fresh()->grand_total,
    );

    $descriptions = $invoice->lines->pluck('description')->all();
    expect($descriptions)->toContain('Coca-Cola 330ml');
});
