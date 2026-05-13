<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Reservation extends Model implements AuditableContract
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use Auditable, HasFactory;

    public const STATUS_PENDING      = 'pending';
    public const STATUS_CONFIRMED    = 'confirmed';
    public const STATUS_CHECKED_IN   = 'checked_in';
    public const STATUS_CHECKED_OUT  = 'checked_out';
    public const STATUS_CANCELLED    = 'cancelled';
    public const STATUS_NO_SHOW      = 'no_show';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
        self::STATUS_CHECKED_OUT,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    public const PAYMENT_UNPAID        = 'unpaid';
    public const PAYMENT_PARTIAL       = 'partial';
    public const PAYMENT_PAID          = 'paid';
    public const PAYMENT_REFUNDED      = 'refunded';
    public const PAYMENT_PLATFORM_PAID = 'platform_paid';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_UNPAID,
        self::PAYMENT_PARTIAL,
        self::PAYMENT_PAID,
        self::PAYMENT_REFUNDED,
        self::PAYMENT_PLATFORM_PAID,
    ];

    public const SOURCE_DIRECT   = 'direct';
    public const SOURCE_PHONE    = 'phone';
    public const SOURCE_WALK_IN  = 'walk_in';
    public const SOURCE_BOOKING  = 'booking';
    public const SOURCE_AIRBNB   = 'airbnb';
    public const SOURCE_EXPEDIA  = 'expedia';
    public const SOURCE_WEBSITE  = 'website';
    public const SOURCE_OTHER    = 'other';

    public const SOURCES = [
        self::SOURCE_DIRECT,
        self::SOURCE_PHONE,
        self::SOURCE_WALK_IN,
        self::SOURCE_BOOKING,
        self::SOURCE_AIRBNB,
        self::SOURCE_EXPEDIA,
        self::SOURCE_WEBSITE,
        self::SOURCE_OTHER,
    ];

    protected $fillable = [
        'code',
        'property_id',
        'guest_id',
        'room_id',
        'room_type_id',
        'check_in_date',
        'check_out_date',
        'nights',
        'adults',
        'children',
        'source',
        'external_reference',
        'status',
        'payment_status',
        'room_rate_total',
        'extras_total',
        'taxes_total',
        'discount_total',
        'grand_total',
        'paid_total',
        'currency',
        'deposit_amount',
        'deposit_paid_at',
        'special_requests',
        'internal_notes',
        'checked_in_at',
        'checked_out_at',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'nights' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'room_rate_total' => 'decimal:2',
            'extras_total' => 'decimal:2',
            'taxes_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_paid_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leadGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'reservation_guests')
            ->withPivot('is_lead')
            ->withTimestamps();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function nightsBreakdown(): HasMany
    {
        return $this->hasMany(ReservationNight::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ReservationCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class)
            ->orderBy('changed_at');
    }

    public function availability(): HasMany
    {
        return $this->hasMany(AvailabilityCalendar::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
