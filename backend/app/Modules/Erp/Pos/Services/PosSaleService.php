<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Services;

use App\Modules\Erp\Inventory\Models\Product;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Inventory\Models\SerialItem;
use App\Modules\Erp\Inventory\Services\StockLedger;
use App\Modules\Erp\Pos\Exceptions\PaymentMismatchException;
use App\Modules\Erp\Pos\Exceptions\ShiftNotOpenException;
use App\Modules\Erp\Pos\Models\PosPayment;
use App\Modules\Erp\Pos\Models\PosSale;
use App\Modules\Erp\Pos\Models\PosSaleItem;
use App\Modules\Erp\Pos\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers a retail sale from the POS single window. Each line snapshots
 * the product weighted-average cost (COGS) and extracts VAT from the gross
 * price; stock is issued through the ledger. The sale is idempotent on
 * sale_uuid so an offline retry returns the original sale instead of
 * double-ringing. Fiscalization (RS.ge) and card capture are deferred to S3,
 * so fiscal_status stays pending here and is never assumed successful.
 */
final class PosSaleService
{
    public function __construct(
        private readonly StockLedger $ledger,
    ) {}

    /**
     * @param list<array{variant_id:int, qty:int, unit_price:float, discount?:float, serial_item_id?:int|null}> $lines
     * @param list<array{method:string, amount:float, terminal_txn_id?:string|null}> $payments
     * @param array{sale_uuid?:string, customer_id?:int|null, channel?:string} $opts
     */
    public function register(PosShift $shift, array $lines, array $payments, array $opts = []): PosSale
    {
        if (! $shift->isOpen()) {
            throw ShiftNotOpenException::for((int) $shift->id);
        }

        $saleUuid = $opts['sale_uuid'] ?? (string) Str::uuid();

        $existing = PosSale::query()->where('sale_uuid', $saleUuid)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($shift, $lines, $payments, $opts, $saleUuid): PosSale {
            $sale = PosSale::create([
                'sale_uuid' => $saleUuid,
                'shift_id' => $shift->id,
                'branch_id' => $shift->branch_id,
                'cashier_id' => $shift->user_id,
                'customer_id' => $opts['customer_id'] ?? null,
                'channel' => $opts['channel'] ?? 'retail',
                'status' => PosSale::STATUS_COMPLETED,
                'fiscal_status' => PosSale::FISCAL_PENDING,
            ]);

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $vatTotal = 0.0;
            $total = 0.0;

            foreach ($lines as $line) {
                $variant = ProductVariant::query()->findOrFail($line['variant_id']);
                $product = Product::query()->findOrFail($variant->product_id);

                $qty = (int) $line['qty'];
                $unitPrice = round((float) $line['unit_price'], 2);
                $lineDiscount = round((float) ($line['discount'] ?? 0.0), 2);
                $gross = round($unitPrice * $qty - $lineDiscount, 2);
                $vat = VatCalculator::extract($gross, (bool) $product->vat_applicable);
                $cost = round((float) $product->cost, 2);

                $item = PosSaleItem::create([
                    'sale_id' => $sale->id,
                    'product_variant_id' => $variant->id,
                    'serial_item_id' => $line['serial_item_id'] ?? null,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $lineDiscount,
                    'vat' => $vat,
                    'cost' => $cost,
                ]);

                $this->ledger->issue(
                    (int) $variant->id,
                    (int) $shift->branch_id,
                    $qty,
                    $cost,
                    $sale,
                    (int) $shift->user_id,
                );

                if (($line['serial_item_id'] ?? null) !== null) {
                    SerialItem::query()->whereKey($line['serial_item_id'])->update([
                        'status' => SerialItem::STATUS_SOLD,
                        'sale_item_id' => $item->id,
                    ]);
                }

                $subtotal += $unitPrice * $qty;
                $discountTotal += $lineDiscount;
                $vatTotal += $vat;
                $total += $gross;
            }

            $sale->subtotal = round($subtotal, 2);
            $sale->discount = round($discountTotal, 2);
            $sale->vat = round($vatTotal, 2);
            $sale->total = round($total, 2);
            $sale->save();

            $this->capturePayments($sale, $payments);

            return $sale;
        });
    }

    /**
     * @param list<array{method:string, amount:float, terminal_txn_id?:string|null}> $payments
     */
    private function capturePayments(PosSale $sale, array $payments): void
    {
        $paid = round(array_sum(array_map(fn (array $p): float => (float) $p['amount'], $payments)), 2);

        if ($paid !== (float) $sale->total) {
            throw PaymentMismatchException::for((float) $sale->total, $paid);
        }

        foreach ($payments as $payment) {
            PosPayment::create([
                'sale_id' => $sale->id,
                'method' => $payment['method'],
                'amount' => round((float) $payment['amount'], 2),
                'terminal_txn_id' => $payment['terminal_txn_id'] ?? null,
                'status' => PosPayment::STATUS_CAPTURED,
            ]);
        }
    }
}
