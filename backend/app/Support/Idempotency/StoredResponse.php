<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

final readonly class StoredResponse
{
    public function __construct(
        public int $status,
        public string $bodySha,
        public string $body,
        public string $contentType,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'body_sha' => $this->bodySha,
            'body' => $this->body,
            'content_type' => $this->contentType,
        ];
    }

    /**
     * @param  array<string, mixed>  $blob
     */
    public static function fromArray(array $blob): self
    {
        return new self(
            status: (int) $blob['status'],
            bodySha: (string) $blob['body_sha'],
            body: (string) $blob['body'],
            contentType: (string) ($blob['content_type'] ?? 'application/json'),
        );
    }
}
