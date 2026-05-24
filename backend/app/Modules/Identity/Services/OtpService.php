<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Identity\Exceptions\InvalidOtpException;
use App\Modules\Identity\Exceptions\OtpDeliveryFailedException;
use App\Modules\Identity\Exceptions\OtpThrottledException;
use App\Modules\Identity\Models\PhoneVerification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Generates, dispatches and verifies SMS OTPs. The persisted record is
 * the source of truth; the SMS gateway is best-effort.
 */
final class OtpService
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function request(string $phoneE164, string $purpose, ?string $ip = null, ?string $ua = null): PhoneVerification
    {
        $latest = PhoneVerification::query()
            ->where('phone_e164', $phoneE164)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        $cooldown = (int) config('sms.otp.resend_cooldown_seconds', 60);
        if ($latest && $latest->sent_at->addSeconds($cooldown)->isFuture()) {
            throw new OtpThrottledException('OTP cooldown active.', [
                'retry_after_seconds' => $latest->sent_at->addSeconds($cooldown)->diffInSeconds(now()),
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) config('sms.otp.ttl_minutes', 5);

        $row = PhoneVerification::create([
            'phone_e164' => $phoneE164,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'attempts' => 0,
            'sent_at' => CarbonImmutable::now(),
            'expires_at' => CarbonImmutable::now()->addMinutes($ttlMinutes),
            'ip' => $ip,
            'user_agent' => $ua ? substr($ua, 0, 255) : null,
        ]);

        $body = sprintf('%s: %s. %s', config('app.name'), $code, __('Code expires in :min minutes.', ['min' => $ttlMinutes]));
        $result = $this->sms->send($phoneE164, $body, $purpose);

        if (! $result->sent) {
            Log::channel('sms')->warning('OTP delivery failed', [
                'phone' => $phoneE164,
                'error' => $result->error,
            ]);

            $row->update(['consumed_at' => now()]);

            throw new OtpDeliveryFailedException(
                'SMS კოდის გაგზავნა ვერ მოხერხდა. გთხოვთ სცადოთ თავიდან.',
                [
                    'provider' => (string) config('sms.driver', 'unknown'),
                    'reason' => $result->error,
                ],
            );
        }

        return $row;
    }

    /**
     * Returns the consumed record on success, or throws.
     */
    public function verify(string $phoneE164, string $code, string $purpose): PhoneVerification
    {
        $row = PhoneVerification::query()
            ->where('phone_e164', $phoneE164)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $maxAttempts = (int) config('sms.otp.max_attempts', 5);

        if (! $row) {
            throw new InvalidOtpException('No active OTP for this phone.');
        }

        if ($row->attempts >= $maxAttempts) {
            $row->update(['consumed_at' => now()]);
            throw new InvalidOtpException('Too many attempts. Request a new code.');
        }

        $row->increment('attempts');

        if (! Hash::check($code, $row->code_hash)) {
            throw new InvalidOtpException('Code does not match.');
        }

        $row->update(['consumed_at' => now()]);

        return $row;
    }
}
