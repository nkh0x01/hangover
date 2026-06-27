<?php

declare(strict_types=1);

namespace App\Modules\Erp\Integration;

use App\Modules\Erp\Integration\Contracts\IntegrationLogger;
use App\Modules\Erp\Integration\Dto\IntegrationResult;
use App\Modules\Erp\Integration\Exceptions\IntegrationFailedException;
use App\Modules\Erp\Integration\Exceptions\VerificationFailedException;
use Throwable;

/**
 * Base for every external integration (RS.ge fiscal/waybill/invoice, FINA,
 * card terminal, Glovo, Wolt).
 *
 * The single rule it enforces: a remote "success" is NEVER trusted on its
 * own. Every call must be paired with a verify step that independently
 * re-reads the remote (or local) state and confirms the real data change.
 * If the remote says OK but verification fails, the call is treated as a
 * silent failure — logged and thrown, never recorded as done.
 */
abstract class AbstractGateway
{
    public function __construct(
        protected readonly IntegrationLogger $logger,
    ) {}

    abstract public function provider(): string;

    /**
     * Whether the decoded remote response indicates success. Each gateway
     * knows its own contract (HTTP status, status field, error codes).
     *
     * @param array<string, mixed> $response
     */
    abstract protected function isSuccess(array $response): bool;

    /**
     * Optional external reference extracted from a successful, verified
     * response (e.g. fiscal receipt no, waybill id, txn id).
     *
     * @param array<string, mixed> $response
     */
    protected function referenceFrom(array $response): ?string
    {
        return null;
    }

    /**
     * Execute an operation under the verify-or-fail contract.
     *
     * @param array<string, mixed> $request
     * @param callable():array<string, mixed> $perform performs the remote call, returns the decoded response
     * @param callable(array<string, mixed>):bool $verify re-checks the real data change after success
     *
     * @throws IntegrationFailedException when the remote did not report success
     * @throws VerificationFailedException when the remote reported success but the change is unverified
     */
    protected function run(
        string $operation,
        array $request,
        callable $perform,
        callable $verify,
        ?string $idempotencyKey = null,
    ): IntegrationResult {
        /** @var array<string, mixed> $response */
        $response = [];
        $success = false;
        $verified = false;
        $reference = null;
        $error = null;

        try {
            $response = $perform();
            $success = $this->isSuccess($response);
            // Verification only runs once the remote claims success; a failed
            // remote call has nothing to verify.
            $verified = $success ? (bool) $verify($response) : false;
            $reference = ($success && $verified) ? $this->referenceFrom($response) : null;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $success = false;
            $verified = false;
        }

        $this->logger->log(
            $this->provider(),
            $operation,
            $request,
            $response,
            $success,
            $verified,
            $idempotencyKey,
            $reference,
            $error,
        );

        if (! $success) {
            throw IntegrationFailedException::for($this->provider(), $operation, $error);
        }

        if (! $verified) {
            throw VerificationFailedException::for($this->provider(), $operation);
        }

        return new IntegrationResult($success, $verified, $response, $reference);
    }
}
