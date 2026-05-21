<?php

declare(strict_types=1);

namespace App\Modules\Geo\Contracts;

final readonly class RouteResult
{
    public function __construct(
        public float $distanceM,
        public int $durationS,
        public string $polyline,
    ) {}
}
