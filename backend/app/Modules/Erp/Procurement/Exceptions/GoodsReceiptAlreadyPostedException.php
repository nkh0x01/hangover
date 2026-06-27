<?php

declare(strict_types=1);

namespace App\Modules\Erp\Procurement\Exceptions;

use App\Support\Exceptions\DomainException;

/**
 * A goods receipt may be posted exactly once. Re-posting would double-count
 * stock and corrupt the weighted-average cost, so it is refused.
 */
final class GoodsReceiptAlreadyPostedException extends DomainException
{
    public static function for(int $goodsReceiptId): self
    {
        return new self(
            sprintf('Goods receipt %d is already posted.', $goodsReceiptId),
            ['goods_receipt_id' => $goodsReceiptId],
        );
    }

    public function code(): string
    {
        return 'procurement.goods_receipt_already_posted';
    }

    public function status(): int
    {
        return 409;
    }
}
