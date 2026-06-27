<?php

declare(strict_types=1);

namespace App\Modules\Erp\Pos\Exceptions;

use App\Support\Exceptions\DomainException;

/**
 * The sum of tendered payments must equal the sale total. A mismatch is
 * refused rather than silently over/under-ringing.
 */
final class PaymentMismatchException extends DomainException
{
    public static function for(float $total, float $paid): self
    {
        return new self(
            sprintf('Payments (%.2f) do not match sale total (%.2f).', $paid, $total),
            ['total' => $total, 'paid' => $paid],
        );
    }

    public function code(): string
    {
        return 'pos.payment_mismatch';
    }

    public function status(): int
    {
        return 422;
    }
}
