<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Payment extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use Auditable, HasFactory;

    public const METHOD_CASH          = 'cash';
    public const METHOD_CARD          = 'card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_PLATFORM      = 'platform';
    public const METHOD_OTHER         = 'other';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_PLATFORM,
        self::METHOD_OTHER,
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED  = 'refunded';
    public const STATUS_FAILED    = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_REFUNDED,
        self::STATUS_FAILED,
    ];

    public const SOURCE_RESERVATION = 'reservation';
    public const SOURCE_POS         = 'pos';

    protected $fillable = [
        'property_id',
        'reservation_id',
        'method',
        'amount',
        'currency',
        'status',
        'source',
        'reference',
        'note',
        'paid_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
