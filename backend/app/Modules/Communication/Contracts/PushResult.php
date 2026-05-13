<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

/**
 * Lightweight value object returned by {@see PushGateway::send()}.
 * Mirrors the shape of {@see SmsResult} so callers can branch on
 * `delivered` without depending on a vendor SDK type.
 */
final readonly class PushResult
{
    public function __construct(
        public bool $delivered,
        public ?string $messageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        /** @var bool When true, the gateway signalled the token is permanently invalid and should be purged. */
        public bool $tokenInvalid = false,
    ) {}

    public static function ok(string $messageId): self
    {
        return new self(delivered: true, messageId: $messageId);
    }

    public static function failed(string $code, string $message, bool $tokenInvalid = false): self
    {
        return new self(
            delivered: false,
            errorCode: $code,
            errorMessage: $message,
            tokenInvalid: $tokenInvalid,
        );
    }
}
