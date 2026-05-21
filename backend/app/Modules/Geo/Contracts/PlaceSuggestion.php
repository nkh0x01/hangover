<?php

declare(strict_types=1);

namespace App\Modules\Geo\Contracts;

final readonly class PlaceSuggestion
{
    public function __construct(
        public string $placeId,
        public string $title,
        public ?string $subtitle = null,
    ) {}
}
