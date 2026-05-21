<?php

declare(strict_types=1);

use App\Modules\Riding\Models\Ride;
use Illuminate\Support\Facades\Broadcast;

/**
 * Per-ride channel. Authorized for the customer of the ride and (once
 * assigned) the driver of the ride. Anyone else gets 403.
 */
Broadcast::channel('private-ride.{rideUlid}', function ($user, string $rideUlid): bool {
    if (! $user) {
        return false;
    }

    $ride = Ride::query()->where('ulid', $rideUlid)->first();
    if (! $ride) {
        return false;
    }

    if ($ride->customer_id === $user->id) {
        return true;
    }

    // Driver authorization through the driver row.
    return $ride->driver_id !== null
        && $ride->driver()->whereHas('user', fn ($q) => $q->whereKey($user->id))->exists();
});
