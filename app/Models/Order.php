<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    protected $guarded = ['id'];

    protected $casts = [
        'items_json'    => 'array',
        'subtotal'      => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
        'total'         => 'decimal:2',
        'confirmed_at'  => 'datetime',
        'paid_at'       => 'datetime',
        'fulfilled_at'  => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
