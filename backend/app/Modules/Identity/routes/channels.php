<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

/**
 * Customer's own private channel — used for wallet, promo grant, and
 * account.* events.
 */
Broadcast::channel('private-customer.{userUlid}', function ($user, string $userUlid): bool {
    return $user !== null && $user->ulid === $userUlid;
});
