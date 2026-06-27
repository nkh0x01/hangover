<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Services;

use App\Modules\Erp\Pos\Models\CashMovement;
use App\Modules\Erp\Pos\Models\PosPayment;
use App\Modules\Erp\Pos\Models\PosSale;
use App\Modules\Erp\Pos\Models\PosShift;
use Illuminate\Support\Facades\DB;

/**
 * Opens shifts and produces X (read-only mid-shift) and Z (closing) reports.
 * The cash line is reconstructed from opening float + cash takings + cash
 * movements so the drawer can be reconciled against the counted amount.
 */
final class PosShiftService
{
    public function open(int $branchId, int $userId, float $openingCash = 0.0): PosShift
    {
        return PosShift::create([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'status' => PosShift::STATUS_OPEN,
            'opening_cash' => $openingCash,
            'opened_at' => now(),
        ]);
    }

    /**
     * Mid-shift snapshot. Persisted on the shift but does not close it.
     *
     * @return array<string, mixed>
     */
    public function xReport(PosShift $shift): array
    {
        $report = $this->buildReport($shift);

        $shift->x_report = $report;
        $shift->save();

        return $report;
    }

    /**
     * Closes the shift with the counted drawer amount and stores the Z report
     * including the over/short variance against expected cash.
     */
    public function close(PosShift $shift, float $countedCash): PosShift
    {
        return DB::transaction(function () use ($shift, $countedCash): PosShift {
            $report = $this->buildReport($shift);
            $report['counted_cash'] = round($countedCash, 2);
            $report['cash_variance'] = round($countedCash - (float) $report['expected_cash'], 2);

            $shift->z_report = $report;
            $shift->closing_cash = $countedCash;
            $shift->status = PosShift::STATUS_CLOSED;
            $shift->closed_at = now();
            $shift->save();

            return $shift;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(PosShift $shift): array
    {
        $sales = PosSale::query()
            ->where('shift_id', $shift->id)
            ->where('status', PosSale::STATUS_COMPLETED);

        $salesCount = (clone $sales)->count();
        $gross = (float) (clone $sales)->sum('total');
        $vat = (float) (clone $sales)->sum('vat');
        $discount = (float) (clone $sales)->sum('discount');

        $saleIds = (clone $sales)->pluck('id');

        $byMethod = PosPayment::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($v): float => round((float) $v, 2))
            ->all();

        $cashSales = (float) ($byMethod[PosPayment::METHOD_CASH] ?? 0.0);

        $cashIn = (float) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('type', CashMovement::TYPE_IN)
            ->sum('amount');

        $cashOut = (float) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->whereIn('type', [CashMovement::TYPE_OUT, CashMovement::TYPE_PAYOUT, CashMovement::TYPE_DEPOSIT])
            ->sum('amount');

        return [
            'sales_count' => $salesCount,
            'gross_total' => round($gross, 2),
            'vat_total' => round($vat, 2),
            'discount_total' => round($discount, 2),
            'by_method' => $byMethod,
            'opening_cash' => round((float) $shift->opening_cash, 2),
            'cash_in' => round($cashIn, 2),
            'cash_out' => round($cashOut, 2),
            'expected_cash' => round((float) $shift->opening_cash + $cashSales + $cashIn - $cashOut, 2),
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
