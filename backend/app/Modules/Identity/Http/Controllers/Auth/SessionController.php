<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Actions\Logout;
use App\Modules\Identity\Actions\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SessionController extends Controller
{
    public function refresh(Request $request, RefreshToken $action): JsonResponse
    {
        $user = $request->user();
        $device = $user->devices()
            ->where('device_uuid', (string) $request->header('X-Device-Id'))
            ->firstOrFail();

        $payload = $action->execute($user, $device);

        return new JsonResponse(['data' => $payload]);
    }

    public function logout(Request $request, Logout $action): JsonResponse
    {
        $action->execute(
            user: $request->user(),
            deviceUuid: (string) $request->header('X-Device-Id'),
        );

        return new JsonResponse(['data' => ['logged_out' => true]]);
    }
}
