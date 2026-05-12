<?php

namespace App\Domain\Billing\Support;

use App\Models\Invoice;
use App\Models\Property;

class InvoiceNumberGenerator
{
    /**
     * Sequential per (property, year): "<PREFIX>-2026-000123".
     * Caller must hold a transaction; we lock the highest existing row
     * to avoid two concurrent invoice generators picking the same number.
     */
    public function next(Property $property): string
    {
        $prefix = $property->settings['invoice_number_prefix'] ?? 'INV';
        $year   = now($property->timezone)->year;

        $latest = Invoice::query()
            ->where('property_id', $property->id)
            ->where('number', 'like', sprintf('%s-%d-%%', $prefix, $year))
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($latest) {
            $tail = (int) substr((string) $latest->number, strrpos((string) $latest->number, '-') + 1);
            $nextSeq = $tail + 1;
        }

        return sprintf('%s-%d-%06d', $prefix, $year, $nextSeq);
    }
}
