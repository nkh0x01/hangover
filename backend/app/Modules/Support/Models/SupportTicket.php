<?php

declare(strict_types=1);

namespace App\Modules\Support\Models;

use App\Support\Ulid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'ulid', 'user_id', 'ride_id', 'category', 'subject', 'status', 'priority',
        'assigned_to_user_id', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $ticket->ulid ??= Ulid::new();
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
