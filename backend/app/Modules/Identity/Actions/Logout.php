<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

final class Logout
{
    public function execute(User $user, string $deviceUuid): void
    {
        $user->tokens()
            ->where('name', 'like', "pat:%:$deviceUuid")
            ->delete();

        $user->devices()
            ->where('device_uuid', $deviceUuid)
            ->update(['revoked_at' => now()]);
    }
}
