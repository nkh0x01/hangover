<?php

namespace App\Domain\Exceptions;

class AvailabilityLedgerCorrupt extends DomainException
{
    public static function missingRows(int $roomId, array $missingDates): self
    {
        return new self(sprintf(
            'Availability ledger is missing rows for room #%d on: %s. '.
            'Call AvailabilityService::ensureRowsExist() before reserving.',
            $roomId,
            implode(', ', $missingDates),
        ));
    }
}
