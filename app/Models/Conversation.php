<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    public const STATUS_NEW                 = 'new';
    public const STATUS_INTERESTED          = 'interested';
    public const STATUS_PRODUCT_RECOMMENDED = 'product_recommended';
    public const STATUS_WAITING             = 'waiting';
    public const STATUS_PAYMENT_PENDING     = 'payment_pending';
    public const STATUS_ORDER_CREATED       = 'order_created';
    public const STATUS_CONVERTED           = 'converted';
    public const STATUS_ESCALATED           = 'escalated';
    public const STATUS_LOST                = 'lost';

    protected $guarded = ['id'];

    protected $casts = [
        'ai_paused'         => 'bool',
        'escalated'         => 'bool',
        'context_json'      => 'array',
        'last_inbound_at'   => 'datetime',
        'last_outbound_at'  => 'datetime',
        'last_followup_at'  => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function isAIEnabled(): bool
    {
        return ! $this->ai_paused && ! $this->escalated;
    }

    public function patchContext(array $patch): void
    {
        $this->context_json = array_replace_recursive($this->context_json ?? [], $patch);
        $this->save();
    }
}
