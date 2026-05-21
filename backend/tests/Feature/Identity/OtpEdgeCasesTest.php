<?php

declare(strict_types=1);

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Contracts\SmsResult;
use App\Modules\Identity\Exceptions\InvalidOtpException;
use App\Modules\Identity\Exceptions\OtpThrottledException;
use App\Modules\Identity\Models\PhoneVerification;
use App\Modules\Identity\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Stub the SMS gateway so the test doesn't depend on a real provider.
    $this->stubGateway = new class implements SmsGateway
    {
        public function send(string $phoneE164, string $body, string $purpose): SmsResult
        {
            return SmsResult::ok('test-'.bin2hex(random_bytes(4)), 0.0);
        }
    };
    $this->app->instance(SmsGateway::class, $this->stubGateway);
});

it('refuses verification with a wrong code', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $otp->request('+995555000001', 'signup');

    expect(fn () => $otp->verify('+995555000001', '000000', 'signup'))
        ->toThrow(InvalidOtpException::class);
});

it('locks the verification record after max attempts', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $otp->request('+995555000002', 'signup');

    $maxAttempts = (int) config('sms.otp.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        try {
            $otp->verify('+995555000002', '000000', 'signup');
        } catch (Throwable) {
        }
    }

    expect(fn () => $otp->verify('+995555000002', '999999', 'signup'))
        ->toThrow(InvalidOtpException::class);

    $row = PhoneVerification::query()->where('phone_e164', '+995555000002')->first();
    expect($row?->consumed_at)->not->toBeNull();
});

it('refuses verification once the code expires', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $row = $otp->request('+995555000003', 'signup');
    $row->update(['expires_at' => now()->subSecond()]);

    expect(fn () => $otp->verify('+995555000003', '123456', 'signup'))
        ->toThrow(InvalidOtpException::class);
});

it('throttles a second OTP request inside the cooldown window', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $otp->request('+995555000004', 'signup');

    expect(fn () => $otp->request('+995555000004', 'signup'))
        ->toThrow(OtpThrottledException::class);
});

it('allows a second OTP request after the cooldown elapses', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $first = $otp->request('+995555000005', 'signup');
    $cooldown = (int) config('sms.otp.resend_cooldown_seconds', 60);
    $first->update(['sent_at' => now()->subSeconds($cooldown + 1)]);

    $second = $otp->request('+995555000005', 'signup');

    expect($second->id)->not->toBe($first->id);
});

it('marks the verification consumed when the correct code is presented', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $row = $otp->request('+995555000006', 'signup');
    // We don't know the random code, so write a known one directly.
    $row->update(['code_hash' => Hash::make('424242')]);

    $verified = $otp->verify('+995555000006', '424242', 'signup');

    expect($verified->consumed_at)->not->toBeNull();
});
