<?php

namespace App\Domain\Channels\Data;

/**
 * Aggregated outcome of a single sync call. Either a pull (we asked the
 * provider for new reservations) or a push (we sent availability/rates).
 */
final class SyncResult
{
    /**
     * @param  array<int, array<string, mixed>>  $items  optional debug detail per row
     */
    public function __construct(
        public readonly bool $ok,
        public readonly int $processed,
        public readonly int $failed,
        public readonly array $items = [],
        public readonly ?string $error = null,
    ) {
    }

    public static function success(int $processed, array $items = []): self
    {
        return new self(true, $processed, 0, $items);
    }

    public static function partial(int $processed, int $failed, array $items = [], ?string $error = null): self
    {
        return new self(true, $processed, $failed, $items, $error);
    }

    public static function failure(string $error): self
    {
        return new self(false, 0, 0, [], $error);
    }

    public function status(): string
    {
        if (! $this->ok) {
            return 'failed';
        }
        return $this->failed > 0 ? 'partial' : 'success';
    }

    public function summary(): array
    {
        return [
            'processed' => $this->processed,
            'failed' => $this->failed,
            'total' => $this->processed + $this->failed,
        ];
    }
}
