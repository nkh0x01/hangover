<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    /** @use HasFactory<\Database\Factories\GuestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'language',
        'doc_type',
        'doc_number',
        'doc_country',
        'doc_expiry',
        'dob',
        'gender',
        'vip',
        'blacklisted',
        'notes',
        'marketing_opt_in',
    ];

    protected function casts(): array
    {
        return [
            'doc_expiry' => 'date',
            'dob' => 'date',
            'vip' => 'boolean',
            'blacklisted' => 'boolean',
            'marketing_opt_in' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservationsAsLead(): HasMany
    {
        return $this->hasMany(Reservation::class, 'guest_id');
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_guests')
            ->withPivot('is_lead')
            ->withTimestamps();
    }
}
