<?php

declare(strict_types=1);

namespace App\Modules\Driver\Models;

use App\Modules\Geo\Models\City;
use App\Modules\Identity\Models\User;
use Database\Factories\DriverApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 */
class DriverApplication extends Model
{
    use HasFactory;

    protected $table = 'driver_applications';

    protected static function newFactory(): DriverApplicationFactory
    {
        return DriverApplicationFactory::new();
    }

    protected $fillable = [
        'user_id',
        'city_id',
        'driver_id',
        'vehicle_id',
        'status',
        'first_name',
        'last_name',
        'personal_id',
        'phone_e164',
        'email',
        'birth_date',
        'service_zone',
        'driver_type',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'vehicle_plate',
        'engine_cc',
        'insurance_expires_on',
        'inspection_expires_on',
        'information_confirmed',
        'terms_accepted',
        'privacy_accepted',
        'rejection_reason',
        'admin_note',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'insurance_expires_on' => 'date',
            'inspection_expires_on' => 'date',
            'information_confirmed' => 'boolean',
            'terms_accepted' => 'boolean',
            'privacy_accepted' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverApplicationDocument::class, 'application_id');
    }
}
