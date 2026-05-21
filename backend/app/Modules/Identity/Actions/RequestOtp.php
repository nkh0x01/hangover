<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Services\OtpService;
use Illuminate\Http\Request;

final readonly class RequestOtp
{
    public function __construct(private OtpService $otp) {}

    public function execute(string $phoneE164, string $purpose, Request $request): array
    {
        $row = $this->otp->request(
            phoneE164: $phoneE164,
            purpose: $purpose,
            ip: $request->ip(),
            ua: (string) $request->userAgent(),
        );

        $resendAfter = (int) config('sms.otp.resend_cooldown_seconds', 60);

        return [
            'resend_after_seconds' => $resendAfter,
            'expires_at' => $row->expires_at->toIso8601String(),
        ];
    }
}
