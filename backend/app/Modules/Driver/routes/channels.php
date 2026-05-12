<?php

declare(strict_types=1);

use App\Modules\Driver\Models\Driver;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-driver.{driverUlid}', function ($user, string $driverUlid): bool {
    if (! $user) {
        return false;
    }
    $driver = Driver::query()
        ->whereHas('user', fn ($q) => $q->where('ulid', $driverUlid))
        ->first();

    return $driver !== null && $driver->user_id === $user->id;
});
