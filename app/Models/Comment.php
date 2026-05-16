<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'replied'                 => 'bool',
        'escalated'               => 'bool',
        'private_reply_attempted' => 'bool',
        'posted_at'               => 'datetime',
    ];
}
