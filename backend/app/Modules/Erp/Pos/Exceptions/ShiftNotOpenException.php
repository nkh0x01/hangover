<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Exceptions;

use App\Support\Exceptions\DomainException;

/**
 * Sales and cash movements require an open shift. Ringing against a closed
 * (or already-Z-reported) shift is refused so takings always reconcile.
 */
final class ShiftNotOpenException extends DomainException
{
    public static function for(int $shiftId): self
    {
        return new self(
            sprintf('Shift %d is not open.', $shiftId),
            ['shift_id' => $shiftId],
        );
    }

    public function code(): string
    {
        return 'pos.shift_not_open';
    }

    public function status(): int
    {
        return 409;
    }
}
