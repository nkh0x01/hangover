<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationCharge extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationChargeFactory> */
    use HasFactory;

    public const TYPE_ROOM     = 'room';
    public const TYPE_PRODUCT  = 'product';
    public const TYPE_FEE      = 'fee';
    public const TYPE_TAX      = 'tax';
    public const TYPE_DISCOUNT = 'discount';
    public const TYPE_DEPOSIT  = 'deposit';

    public const TYPES = [
        self::TYPE_ROOM,
        self::TYPE_PRODUCT,
        self::TYPE_FEE,
        self::TYPE_TAX,
        self::TYPE_DISCOUNT,
        self::TYPE_DEPOSIT,
    ];

    protected $fillable = [
        'reservation_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'total',
        'taxable',
        'tax_rate',
        'added_by',
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'taxable' => 'boolean',
            'added_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
