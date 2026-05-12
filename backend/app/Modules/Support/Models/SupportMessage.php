<?php

declare(strict_types=1);

namespace App\Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $table = 'support_messages';

    protected $fillable = ['ticket_id', 'sender_user_id', 'body', 'attachments', 'internal_note'];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'internal_note' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
