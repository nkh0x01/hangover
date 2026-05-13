<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryMovementFactory> */
    use HasFactory;

    public const TYPE_PURCHASE   = 'purchase';
    public const TYPE_SALE       = 'sale';
    public const TYPE_TRANSFER   = 'transfer';
    public const TYPE_REFILL     = 'refill';
    public const TYPE_LOSS       = 'loss';
    public const TYPE_DAMAGE     = 'damage';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RETURN     = 'return';

    public const TYPES = [
        self::TYPE_PURCHASE,
        self::TYPE_SALE,
        self::TYPE_TRANSFER,
        self::TYPE_REFILL,
        self::TYPE_LOSS,
        self::TYPE_DAMAGE,
        self::TYPE_ADJUSTMENT,
        self::TYPE_RETURN,
    ];

    protected $fillable = [
        'property_id', 'product_id',
        'from_location_id', 'to_location_id',
        'type', 'quantity', 'unit_cost',
        'reservation_id', 'payment_id',
        'user_id', 'note', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
