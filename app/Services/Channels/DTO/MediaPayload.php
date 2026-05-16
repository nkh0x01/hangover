<?php

namespace App\Services\Channels\DTO;

final class MediaPayload
{
    public function __construct(
        public readonly string  $kind,    // image|video|audio|document
        public readonly string  $url,
        public readonly ?string $caption = null,
        public readonly ?string $mime    = null,
    ) {}

    public static function image(string $url, ?string $caption = null): self
    {
        return new self('image', $url, $caption);
    }
}
