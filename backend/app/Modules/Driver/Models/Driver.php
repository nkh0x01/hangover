<?php

declare(strict_types=1);

namespace App\Modules\Driver\Models;

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $city_id
 * @property string $status
 * @property bool $online
 * @property Carbon|null $online_since
 * @property int|null $current_vehicle_id
 * @property string $rating_avg
 * @property User $user
 * @property Vehicle|null $currentVehicle
 */
class Driver extends Model
{
    use HasFactory;

    protected $table = 'drivers';

    protected static function newFactory(): DriverFactory
    {
        return DriverFactory::new();
    }

    protected $fillable = [
        'user_id',
        'city_id',
        'status',
        'approval_notes',
        'approved_at',
        'approved_by_user_id',
        'online',
        'online_since',
        'current_vehicle_id',
        'rating_avg',
        'rating_count',
        'trips_completed',
        'commission_rate_override',
        'id_number_encrypted',
        'iban_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'online' => 'boolean',
            'online_since' => 'datetime',
            'approved_at' => 'datetime',
            'rating_avg' => 'decimal:2',
            'commission_rate_override' => 'decimal:4',
            'id_number_encrypted' => 'encrypted',
            'iban_encrypted' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'current_vehicle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(DriverShift::class);
    }

    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }
}
