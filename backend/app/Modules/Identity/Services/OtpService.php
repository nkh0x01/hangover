<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Contracts\SmsResult;
use App\Modules\Communication\Models\SmsLog;
use App\Modules\Identity\Exceptions\InvalidOtpException;
use App\Modules\Identity\Exceptions\OtpDeliveryFailedException;
use App\Modules\Identity\Exceptions\OtpThrottledException;
use App\Modules\Identity\Models\PhoneVerification;
use App\Support\Phone\GeorgianPhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        $cooldown = (int) config('sms.otp.resend_cooldown_seconds', 60);
        if ($latest && $latest->sent_at->addSeconds($cooldown)->isFuture()) {
            $retryAfter = $latest->sent_at->addSeconds($cooldown)->diffInSeconds(now());
            $this->logSmsAttempt(
                phoneE164: $phoneE164,
                purpose: $purpose,
                result: SmsResult::failure('resend cooldown active'),
                skipReason: 'cooldown',
            );

            throw new OtpThrottledException('სცადეთ მოგვიანებით.', [
                'retry_after_seconds' => $retryAfter,
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) config('sms.otp.ttl_minutes', 5);

        $row = DB::transaction(function () use ($phoneE164, $purpose, $code, $ttlMinutes, $ip, $ua): PhoneVerification {
            PhoneVerification::query()
                ->where('phone_e164', $phoneE164)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return PhoneVerification::create([
                'phone_e164' => $phoneE164,
                'code_hash' => Hash::make($code),
                'purpose' => $purpose,
                'attempts' => 0,
                'sent_at' => CarbonImmutable::now(),
                'expires_at' => CarbonImmutable::now()->addMinutes($ttlMinutes),
                'ip' => $ip,
                'user_agent' => $ua ? substr($ua, 0, 255) : null,
            ]);
        });

        $body = sprintf('%s: %s. %s', config('app.name'), $code, __('Code expires in :min minutes.', ['min' => $ttlMinutes]));
        $result = $this->sms->send($phoneE164, $body, $purpose);
        $this->logSmsAttempt($phoneE164, $purpose, $result);

        if (! $result->sent) {
            Log::channel('sms')->warning('OTP delivery failed', [
                'phone' => GeorgianPhoneNumber::mask($phoneE164),
                'error' => $result->error,
            ]);

            $row->update(['consumed_at' => now()]);

            throw new OtpDeliveryFailedException(
                'კოდის გაგზავნა ვერ მოხერხდა.',
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
            throw new InvalidOtpException('კოდის ვადა ამოიწურა.');
        }

        if ($row->attempts >= $maxAttempts) {
            $row->update(['consumed_at' => now()]);
            throw new InvalidOtpException('სცადეთ მოგვიანებით.');
        }

        $row->increment('attempts');

        if (! Hash::check($code, $row->code_hash)) {
            throw new InvalidOtpException('კოდი არასწორია.');
        }

        $row->update(['consumed_at' => now()]);

        return $row;
    }

    private function logSmsAttempt(
        string $phoneE164,
        string $purpose,
        SmsResult $result,
        ?string $skipReason = null,
    ): void {
        try {
            SmsLog::query()->create([
                'phone_e164' => $phoneE164,
                'destination' => $phoneE164,
                'masked_phone' => GeorgianPhoneNumber::mask($phoneE164),
                'message_type' => 'otp',
                'purpose' => $purpose,
                'provider' => (string) config('sms.driver', 'unknown'),
                'provider_msg_id' => $result->providerMessageId,
                'provider_response' => $result->providerMessageId,
                'cost' => $result->cost,
                'status' => $result->sent ? 'sent' : 'failed',
                'error_reason' => $result->error,
                'skip_reason' => $skipReason,
                'sent_at' => $result->sent ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::channel('sms')->warning('SMS attempt logging failed', [
                'phone' => GeorgianPhoneNumber::mask($phoneE164),
                'purpose' => $purpose,
                'provider' => (string) config('sms.driver', 'unknown'),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
