<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Auth;

use App\Modules\Identity\Actions\RequestOtp;
use App\Modules\Identity\Actions\VerifyOtp;
use App\Modules\Identity\Http\Requests\OtpRequestRequest;
use App\Modules\Identity\Http\Requests\OtpVerifyRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class OtpController extends Controller
{
    public function request(OtpRequestRequest $request, RequestOtp $action): JsonResponse
    {
        $payload = $action->execute(
            phoneE164: (string) $request->validated('phone'),
            purpose: (string) $request->validated('purpose'),
            request: $request,
        );

        return new JsonResponse(['data' => $payload], 202);
    }

    public function verify(OtpVerifyRequest $request, VerifyOtp $action): JsonResponse
    {
        $result = $action->execute(
            phoneE164: (string) $request->validated('phone'),
            code: (string) $request->validated('code'),
            purpose: (string) $request->validated('purpose'),
            deviceMeta: [
                'device_uuid' => $request->validated('device_uuid'),
                'platform' => $request->validated('platform'),
                'app_version' => $request->validated('app_version'),
                'os_version' => $request->validated('os_version'),
                'fcm_token' => $request->validated('fcm_token'),
                'voip_token' => $request->validated('voip_token'),
            ],
        );

        return new JsonResponse([
            'data' => [
                'token' => $result['token'],
                'expires_at' => $result['expires_at'],
                'abilities' => $result['abilities'],
                'is_new' => $result['is_new'],
                'user' => (new UserResource($result['user']))->toArray($request),
            ],
        ], $result['is_new'] ? 201 : 200);
    }
}
