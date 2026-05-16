<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    public const DIRECTION_IN  = 'inbound';
    public const DIRECTION_OUT = 'outbound';

    protected $guarded = ['id'];

    protected $casts = [
        'is_ai'           => 'bool',
        'media_json'      => 'array',
        'tool_calls_json' => 'array',
        'raw_json'        => 'array',
        'sent_at'         => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_employee_id');
    }
}
