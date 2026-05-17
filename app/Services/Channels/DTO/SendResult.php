<?php

namespace App\Services\Channels\DTO;

final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $platformMsgId = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}

    public static function ok(?string $id = null, array $raw = []): self
    {
        return new self(true, $id, null, $raw);
    }

    public static function fail(string $error, array $raw = []): self
    {
        return new self(false, null, $error, $raw);
    }
}
