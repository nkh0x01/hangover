<?php

declare(strict_types=1);

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Contracts\SmsResult;
use App\Modules\Communication\Models\SmsLog;
use App\Modules\Identity\Exceptions\InvalidOtpException;
use App\Modules\Identity\Exceptions\OtpDeliveryFailedException;
use App\Modules\Identity\Exceptions\OtpThrottledException;
use App\Modules\Identity\Models\PhoneVerification;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\OtpService;
use App\Support\Phone\GeorgianPhoneNumber;
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

it('returns a domain error when OTP SMS delivery fails', function (): void {
    $this->app->instance(SmsGateway::class, new class implements SmsGateway
    {
        public function send(string $phoneE164, string $body, string $purpose): SmsResult
        {
            return SmsResult::failure('provider rejected message');
        }
    });

    /** @var OtpService $otp */
    $otp = app(OtpService::class);

    expect(fn () => $otp->request('+995555000007', 'signup'))
        ->toThrow(OtpDeliveryFailedException::class);

    $row = PhoneVerification::query()->where('phone_e164', '+995555000007')->first();
    expect($row?->consumed_at)->not->toBeNull();
});

it('normalizes Georgian mobile phone inputs to canonical E.164', function (string $input): void {
    expect(GeorgianPhoneNumber::normalize($input))->toBe('+995555123456');
})->with([
    'local mobile' => '555123456',
    'country code without plus' => '995555123456',
    'country code with plus' => '+995 555-123-456',
]);

it('rejects non-Georgian mobile phone inputs', function (string $input): void {
    expect(fn () => GeorgianPhoneNumber::normalize($input))->toThrow(InvalidArgumentException::class);
})->with([
    'landline' => '+995322123456',
    'foreign' => '+15551234567',
    'too short' => '555123',
]);

it('accepts common Georgian phone formats through the OTP request API', function (): void {
    $this->postJson('/api/v1/auth/otp/request', [
        'phone' => '555 123-456',
        'purpose' => 'signup',
    ])->assertAccepted()
        ->assertJsonPath('data.resend_after_seconds', 60);

    expect(PhoneVerification::query()->where('phone_e164', '+995555123456')->exists())->toBeTrue()
        ->and(SmsLog::query()->where('phone_e164', '+995555123456')->where('message_type', 'otp')->exists())->toBeTrue();
});

it('invalidates an older OTP when a new code is requested after cooldown', function (): void {
    /** @var OtpService $otp */
    $otp = app(OtpService::class);
    $first = $otp->request('+995555000008', 'signup');
    $first->update(['sent_at' => now()->subSeconds(((int) config('sms.otp.resend_cooldown_seconds', 60)) + 1)]);

    $second = $otp->request('+995555000008', 'signup');

    expect($first->fresh()->consumed_at)->not->toBeNull()
        ->and($second->fresh()->consumed_at)->toBeNull();
});

it('returns a visible Georgian error when SMS delivery fails through the API', function (): void {
    $this->app->instance(SmsGateway::class, new class implements SmsGateway
    {
        public function send(string $phoneE164, string $body, string $purpose): SmsResult
        {
            return SmsResult::failure('provider rejected message');
        }
    });

    $this->postJson('/api/v1/auth/otp/request', [
        'phone' => '+995555000009',
        'purpose' => 'signup',
    ])->assertStatus(502)
        ->assertJsonPath('error.code', 'auth.otp_delivery_failed')
        ->assertJsonPath('error.message', 'კოდის გაგზავნა ვერ მოხერხდა.');
});

it('verifies OTP through the API and returns a customer token', function (): void {
    $this->postJson('/api/v1/auth/otp/request', [
        'phone' => '+995555000010',
        'purpose' => 'signup',
    ])->assertAccepted();

    PhoneVerification::query()
        ->where('phone_e164', '+995555000010')
        ->latest('id')
        ->firstOrFail()
        ->update(['code_hash' => Hash::make('123456')]);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'phone' => '995555000010',
        'code' => '123456',
        'purpose' => 'signup',
        'device_uuid' => '1cf08c77-39f0-4cb4-a262-720f1fe9a5d4',
        'platform' => 'ios',
        'app_version' => '0.1.0',
    ])->assertCreated()
        ->assertJsonPath('data.user.type', 'customer')
        ->assertJsonStructure(['data' => ['token']]);

    $token = (string) $response->json('data.token');
    $this->withToken($token)->getJson('/api/v1/customer/me')
        ->assertOk()
        ->assertJsonPath('data.phone', '+995555000010');
});

it('returns driver context after driver OTP login', function (): void {
    User::factory()->create([
        'type' => 'customer',
        'phone_e164' => '+995555000011',
    ]);

    $this->postJson('/api/v1/auth/otp/request', [
        'phone' => '+995555000011',
        'purpose' => 'driver_signup',
    ])->assertAccepted();

    PhoneVerification::query()
        ->where('phone_e164', '+995555000011')
        ->latest('id')
        ->firstOrFail()
        ->update(['code_hash' => Hash::make('123456')]);

    $response = $this->postJson('/api/v1/auth/otp/verify', [
        'phone' => '+995555000011',
        'code' => '123456',
        'purpose' => 'driver_signup',
        'device_uuid' => '21f45e13-72b6-41bc-9d18-262f84709577',
        'platform' => 'ios',
        'app_version' => '0.1.0',
    ])->assertOk()
        ->assertJsonPath('data.user.type', 'driver')
        ->assertJsonPath('data.user.driver_context.has_driver_profile', false)
        ->assertJsonPath('data.user.driver_context.needs_application', true)
        ->assertJsonPath('data.user.driver_context.can_submit_application', true)
        ->assertJsonPath('data.user.driver_context.can_go_online', false);

    $this->withToken((string) $response->json('data.token'))->getJson('/api/v1/driver/me')
        ->assertOk()
        ->assertJsonPath('data.driver_context.needs_application', true);
});
