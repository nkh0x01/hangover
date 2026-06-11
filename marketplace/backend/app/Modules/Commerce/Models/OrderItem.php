<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'seller_id',
        'title_snapshot',
        'image_snapshot',
        'unit_price_gel',
        'quantity',
        'line_total_gel',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_gel' => 'decimal:2',
            'line_total_gel' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
