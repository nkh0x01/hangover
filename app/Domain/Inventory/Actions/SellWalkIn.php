<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Billing\Support\InvoiceNumberGenerator;
use App\Domain\Inventory\InventoryService;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reception POS-lite. Sells a cart of products to a walk-in customer:
 *   - records a Payment (no reservation_id)
 *   - generates an Invoice with the same property's sequence
 *   - inserts an inventory_movements row per cart line
 * All inside one transaction.
 *
 * Cart shape:
 *   [ ['product_id' => int, 'quantity' => int], ... ]
 */
class SellWalkIn
{
    public function __construct(
        private readonly InventoryService $service,
        private readonly InvoiceNumberGenerator $numbers,
    ) {
    }

    /**
     * @param array<int, array{product_id: int, quantity: int}> $cart
     */
    public function execute(
        Property $property,
        InventoryLocation $location,
        array $cart,
        string $paymentMethod,
        ?User $actor = null,
        ?string $note = null,
    ): Invoice {
        if (empty($cart)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }
        if (! in_array($paymentMethod, Payment::METHODS, true)) {
            throw new \InvalidArgumentException("Unsupported payment method: {$paymentMethod}");
        }

        return DB::transaction(function () use ($property, $location, $cart, $paymentMethod, $actor, $note) {
            // Resolve and validate products up-front.
            $productIds = array_column($cart, 'product_id');
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('property_id', $property->id)
                ->get()
                ->keyBy('id');

            $lines = [];
            $subtotal = 0.0;
            foreach ($cart as $row) {
                $product = $products->get($row['product_id']);
                if (! $product) {
                    throw new \InvalidArgumentException("Unknown product {$row['product_id']}.");
                }
                $qty = (int) $row['quantity'];
                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Cart quantity must be positive for product {$product->id}.");
                }
                $unit = (float) $product->sale_price;
                $total = round($unit * $qty, 2);
                $subtotal += $total;
                $lines[] = compact('product', 'qty', 'unit', 'total');
            }

            $total = round($subtotal, 2);

            // 1. Invoice header
            $invoice = Invoice::create([
                'property_id' => $property->id,
                'number' => $this->numbers->next($property),
                'reservation_id' => null,
                'source' => Invoice::SOURCE_POS,
                'issued_at' => now(),
                'subtotal' => $total,
                'tax_total' => 0,
                'discount_total' => 0,
                'total' => $total,
                'paid_total' => $total,
                'balance' => 0,
                'currency' => $property->base_currency,
                'status' => Invoice::STATUS_PAID,
                'guest_snapshot' => ['name' => 'Walk-in customer'],
            ]);

            // 2. Payment
            $payment = Payment::create([
                'property_id' => $property->id,
                'reservation_id' => null,
                'method' => $paymentMethod,
                'amount' => $total,
                'currency' => $property->base_currency,
                'status' => Payment::STATUS_COMPLETED,
                'source' => Payment::SOURCE_POS,
                'paid_at' => now(),
                'received_by' => $actor?->id,
                'note' => $note,
            ]);

            // 3. Invoice lines + stock movements
            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'description' => $product->name,
                    'quantity' => $line['qty'],
                    'unit_price' => $line['unit'],
                    'total' => $line['total'],
                    'tax_rate' => (float) $product->tax_rate,
                ]);

                if ($product->track_stock) {
                    $this->service->removeStock(
                        $product, $location, $line['qty'],
                        InventoryMovement::TYPE_SALE,
                        $actor?->id,
                        null,
                        $payment->id,
                        $note,
                    );
                }
            }

            return $invoice->fresh('lines');
        });
    }
}
