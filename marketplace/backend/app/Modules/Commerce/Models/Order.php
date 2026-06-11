<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'number',
        'user_id',
        'status',
        'payment_method',
        'payment_status',
        'subtotal_gel',
        'shipping_gel',
        'total_gel',
        'shipping_name',
        'shipping_phone',
        'shipping_region',
        'shipping_city',
        'shipping_address',
        'shipping_notes',
        'placed_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_gel' => 'decimal:2',
            'shipping_gel' => 'decimal:2',
            'total_gel' => 'decimal:2',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public static function generateNumber(): string
    {
        $cfg = config('marketplace.order_number');
        $year = now()->year;
        $sequence = (self::query()->whereYear('created_at', $year)->count()) + 1;

        return sprintf('%s-%d-%s', $cfg['prefix'], $year, str_pad((string) $sequence, $cfg['pad'], '0', STR_PAD_LEFT));
    }
}
