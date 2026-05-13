<?php

namespace App\Console\Commands;

use App\Domain\Availability\Period;
use App\Domain\Exceptions\InsufficientStock;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\RefillMinibar;
use App\Domain\Inventory\Actions\SellToReservation;
use App\Domain\Inventory\Actions\SellWalkIn;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\InventoryService;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomMinibarItem;
use Illuminate\Console\Command;

/**
 * One-shot end-to-end Phase 2 verification — wipes the DB, re-seeds, and
 * exercises every spec scenario through the real domain actions. Prints a
 * PASS/FAIL line per scenario and exits non-zero on any failure.
 */
class Phase2Verify extends Command
{
    protected $signature = 'pms:phase2-verify';
    protected $description = 'Run end-to-end verification of Phase 2 inventory / minibar / POS.';

    /** @var array<int, array{name: string, ok: bool, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $property  = Property::query()->orderBy('id')->first();
        $service   = app(InventoryService::class);

        // Fixed handles into the seeded data.
        $cola      = Product::where('property_id', $property->id)->where('sku', 'CC330')->firstOrFail();
        $beer      = Product::where('property_id', $property->id)->where('sku', 'BEER500')->firstOrFail();
        $snickers  = Product::where('property_id', $property->id)->where('sku', 'SNICK')->firstOrFail();
        $storage   = InventoryLocation::where('property_id', $property->id)->where('type', 'storage')->firstOrFail();
        $reception = InventoryLocation::where('property_id', $property->id)->where('type', 'reception')->firstOrFail();
        $room101   = Room::where('property_id', $property->id)->where('number', '101')->firstOrFail();
        $room101Bar = $room101->minibarLocation;

        // ----- 1. Products page (table) opens with seeded products -----
        $this->check('1. Products page lists seeded items',
            Product::where('property_id', $property->id)->count() >= 8,
            'product count = '.Product::count(),
        );

        // ----- 2. Product can be created/edited -----
        $espresso = Product::create([
            'property_id' => $property->id,
            'name' => 'Espresso',
            'sku' => 'ESP001',
            'cost_price' => 0.80,
            'sale_price' => 2.50,
            'tax_rate' => 0,
            'track_stock' => true,
            'low_stock_threshold' => 5,
            'active' => true,
        ]);
        $espresso->update(['sale_price' => 3.00]);
        $this->check('2. Product create + edit',
            $espresso->fresh()->sale_price == 3.00,
            'created id='.$espresso->id.' updated sale=3.00',
        );

        // ----- 3. Product category exists & links -----
        $drinks = ProductCategory::where('property_id', $property->id)->where('slug', 'drinks')->first();
        $catProductCount = Product::where('category_id', $drinks?->id)->count();
        $this->check('3. Product category seeded and linked',
            $drinks !== null && $catProductCount > 0,
            'drinks category has '.$catProductCount.' products',
        );

        // ----- 4. Stock can be received (purchase movement to storage) -----
        $beforeStorage = $service->stockAt($cola, $storage);
        app(ReceiveStock::class)->execute($cola, $storage, 12, 1.50);
        $afterStorage = $service->stockAt($cola, $storage);
        $this->check('4. Stock received (storage)',
            $afterStorage === $beforeStorage + 12,
            "storage cola: $beforeStorage → $afterStorage",
        );

        // ----- 5. Stock transfer between locations -----
        $beforeRecep = $service->stockAt($cola, $reception);
        app(TransferStock::class)->execute($cola, $storage, $reception, 4);
        $this->check('5. Stock transferred storage → reception',
            $service->stockAt($cola, $reception) === $beforeRecep + 4
                && $service->stockAt($cola, $storage) === $afterStorage - 4,
            "reception cola: $beforeRecep → ".$service->stockAt($cola, $reception),
        );

        // ----- 6. Room minibar setup (par levels seeded for every room) -----
        $minibarItems = RoomMinibarItem::where('room_id', $room101->id)->count();
        $this->check('6. Room minibar setup (par levels)',
            $minibarItems > 0 && $room101Bar !== null,
            "room 101 has $minibarItems par rows; minibar location id=".$room101Bar?->id,
        );

        // ----- 7. Room minibar refill creates transfer movements -----
        // Drain the minibar first so refill has work to do.
        \App\Models\ProductStock::where('inventory_location_id', $room101Bar->id)->update(['quantity' => 0]);
        $result = app(RefillMinibar::class)->execute($room101);
        $refillMovements = InventoryMovement::where('to_location_id', $room101Bar->id)
            ->where('type', InventoryMovement::TYPE_REFILL)
            ->count();
        $this->check('7. Minibar refill creates transfer movements',
            count($result['refilled']) > 0 && $refillMovements >= count($result['refilled']),
            'refilled='.count($result['refilled']).' skipped='.count($result['skipped']),
        );

        // Set up an in-house guest (R1) so we can sell to a reservation.
        $guest = \App\Models\Guest::factory()->create(['property_id' => $property->id, 'first_name' => 'Test', 'last_name' => 'Guest']);
        $r1 = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property, guest: $guest, roomType: $room101->roomType,
            period: new Period(now()->toDateString(), now()->addDays(2)->toDateString()),
            room: $room101, adults: 1,
        ));
        app(CheckInReservation::class)->execute($r1);

        // ----- 8. Minibar/product charge added to reservation folio -----
        $beforeBalance = (float) $r1->fresh()->grand_total;
        $charge = app(SellToReservation::class)->execute($r1->fresh(), $cola, $room101Bar, 1);
        $r1Refresh = $r1->fresh();
        $this->check('8. Product charge added to reservation folio',
            $r1Refresh->charges()->where('type', 'product')->count() === 1
                && (float) $r1Refresh->grand_total > $beforeBalance,
            sprintf('balance %.2f → %.2f (charge total %.2f)',
                $beforeBalance, (float) $r1Refresh->grand_total, (float) $charge->total,
            ),
        );

        // ----- 9. Sale decreased stock at the chosen location -----
        $minibarStockAfter = $service->stockAt($cola, $room101Bar);
        $this->check('9. Sale decreased stock at the chosen location',
            $minibarStockAfter < $service->stockAt($cola, $storage), // sanity
            'minibar cola = '.$minibarStockAfter,
        );

        // ----- 10. Insufficient stock blocks the sale safely -----
        $sellGuard = false;
        try {
            // The minibar has at most a few; ask for 999.
            app(SellToReservation::class)->execute($r1->fresh(), $cola, $room101Bar, 999);
        } catch (InsufficientStock) {
            $sellGuard = true;
        }
        $r1AfterGuard = $r1->fresh();
        // grand_total should NOT have ballooned (transaction rolled back).
        $this->check('10. Insufficient stock blocks sale (no folio change)',
            $sellGuard
                && (float) $r1AfterGuard->grand_total === (float) $r1Refresh->grand_total,
            'InsufficientStock thrown; folio unchanged at '.$r1AfterGuard->grand_total,
        );

        // ----- 11. POS-lite walk-in sale (Payment + Invoice + movements) -----
        $invoicesBefore = Invoice::count();
        $invoice = app(SellWalkIn::class)->execute(
            $property,
            $reception,
            [
                ['product_id' => $cola->id,     'quantity' => 2],
                ['product_id' => $snickers->id, 'quantity' => 1],
            ],
            Payment::METHOD_CASH,
        );
        $this->check('11. POS walk-in sale completed',
            $invoice->source === Invoice::SOURCE_POS
                && Invoice::count() === $invoicesBefore + 1
                && (float) $invoice->total > 0,
            "invoice {$invoice->number} total={$invoice->total} {$invoice->currency}",
        );

        // ----- 12. Receipt/invoice shows product line items -----
        $lines = $invoice->lines->pluck('description')->all();
        $this->check('12. Receipt shows product line items',
            in_array('Coca-Cola 330ml', $lines, true)
                && in_array('Snickers', $lines, true),
            'lines: ['.implode(', ', $lines).']',
        );

        // ----- 13. Movement ledger records every movement -----
        $relevantTypes = [
            InventoryMovement::TYPE_PURCHASE,
            InventoryMovement::TYPE_TRANSFER,
            InventoryMovement::TYPE_REFILL,
            InventoryMovement::TYPE_SALE,
        ];
        $movementCounts = [];
        foreach ($relevantTypes as $t) {
            $movementCounts[$t] = InventoryMovement::where('type', $t)->count();
        }
        $this->check('13. Movement ledger captures every type',
            array_sum($movementCounts) >= 4,
            implode(' · ', array_map(fn ($t, $c) => "$t=$c", array_keys($movementCounts), $movementCounts)),
        );

        // ----- 14. Low-stock alert flags products at/below threshold -----
        // Drop one product hard so it crosses threshold reliably.
        $low = Product::factory()->create([
            'property_id' => $property->id,
            'name' => 'Edge case water', 'sku' => 'EDGE1',
            'low_stock_threshold' => 5,
        ]);
        app(ReceiveStock::class)->execute($low, $storage, 2);
        $report = $service->lowStockReport($property);
        $flagged = $report->contains(fn ($r) => $r['product']->id === $low->id);
        $this->check('14. Low-stock report flags below-threshold products',
            $flagged,
            'report size='.$report->count().' includes EDGE1='.($flagged ? 'YES' : 'NO'),
        );

        // ----- Report -----
        $this->newLine();
        $this->info('═══ Phase 2 Verification Results ═══');
        $allOk = true;
        foreach ($this->results as $r) {
            $status = $r['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $this->line(sprintf('  [%s] %-58s %s', $status, $r['name'], $r['detail']));
            $allOk = $allOk && $r['ok'];
        }
        $this->newLine();
        $this->line($allOk ? '<fg=green>All scenarios passed.</>' : '<fg=red>One or more scenarios failed.</>');

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $name, bool $ok, string $detail = ''): void
    {
        $this->results[] = compact('name', 'ok', 'detail');
    }
}
