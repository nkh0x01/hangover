<?php

declare(strict_types=1);

use App\Modules\Erp\Integration\AbstractGateway;
use App\Modules\Erp\Integration\Contracts\IntegrationLogger;
use App\Modules\Erp\Integration\Exceptions\IntegrationFailedException;
use App\Modules\Erp\Integration\Exceptions\VerificationFailedException;

/**
 * In-memory logger so the verify-or-fail logic is testable without a DB.
 */
final class RecordingLogger implements IntegrationLogger
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function log(
        string $provider,
        string $operation,
        array $request,
        array $response,
        bool $success,
        bool $verified,
        ?string $idempotencyKey = null,
        ?string $reference = null,
        ?string $error = null,
    ): void {
        $this->rows[] = compact(
            'provider',
            'operation',
            'success',
            'verified',
            'reference',
            'error',
        );
    }
}

/**
 * Concrete gateway whose perform/verify are injected per test, so we can
 * drive every branch of the contract.
 */
final class FakeGateway extends AbstractGateway
{
    public function provider(): string
    {
        return 'rs_fiscal';
    }

    protected function isSuccess(array $response): bool
    {
        return ($response['status'] ?? null) === 'ok';
    }

    protected function referenceFrom(array $response): ?string
    {
        return $response['receipt_no'] ?? null;
    }

    /**
     * @param array<string, mixed> $request
     * @param callable():array<string, mixed> $perform
     * @param callable(array<string, mixed>):bool $verify
     */
    public function call(array $request, callable $perform, callable $verify)
    {
        return $this->run('create_receipt', $request, $perform, $verify);
    }
}

it('returns an ok result when the remote succeeds and the change is verified', function (): void {
    $logger = new RecordingLogger;
    $gateway = new FakeGateway($logger);

    $result = $gateway->call(
        ['total' => 100],
        fn () => ['status' => 'ok', 'receipt_no' => 'FR-123'],
        fn (array $r) => true,
    );

    expect($result->ok())->toBeTrue()
        ->and($result->reference)->toBe('FR-123')
        ->and($logger->rows)->toHaveCount(1)
        ->and($logger->rows[0]['success'])->toBeTrue()
        ->and($logger->rows[0]['verified'])->toBeTrue();
});

it('treats a successful response with no verified data change as a silent failure', function (): void {
    $logger = new RecordingLogger;
    $gateway = new FakeGateway($logger);

    // Remote says "ok" but the re-read shows the change never landed.
    expect(fn () => $gateway->call(
        ['total' => 100],
        fn () => ['status' => 'ok', 'receipt_no' => 'FR-123'],
        fn (array $r) => false,
    ))->toThrow(VerificationFailedException::class);

    expect($logger->rows)->toHaveCount(1)
        ->and($logger->rows[0]['success'])->toBeTrue()
        ->and($logger->rows[0]['verified'])->toBeFalse()
        // a silent failure must never record an external reference
        ->and($logger->rows[0]['reference'])->toBeNull();
});

it('fails when the remote does not report success and skips verification', function (): void {
    $logger = new RecordingLogger;
    $gateway = new FakeGateway($logger);
    $verifyRan = false;

    expect(fn () => $gateway->call(
        ['total' => 100],
        fn () => ['status' => 'error'],
        function (array $r) use (&$verifyRan): bool {
            $verifyRan = true;

            return true;
        },
    ))->toThrow(IntegrationFailedException::class);

    expect($verifyRan)->toBeFalse()
        ->and($logger->rows[0]['success'])->toBeFalse()
        ->and($logger->rows[0]['verified'])->toBeFalse();
});

it('captures transport exceptions as a logged failure', function (): void {
    $logger = new RecordingLogger;
    $gateway = new FakeGateway($logger);

    expect(fn () => $gateway->call(
        ['total' => 100],
        fn () => throw new RuntimeException('connection refused'),
        fn (array $r) => true,
    ))->toThrow(IntegrationFailedException::class);

    expect($logger->rows[0]['success'])->toBeFalse()
        ->and($logger->rows[0]['error'])->toBe('connection refused');
});
