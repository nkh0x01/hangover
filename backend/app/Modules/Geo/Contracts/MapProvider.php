<?php

declare(strict_types=1);

namespace App\Modules\Geo\Contracts;

use App\Support\Geo\Point;

interface MapProvider
{
    public function routing(Point $from, Point $to): RouteResult;

    public function eta(Point $from, Point $to): int;

    /**
     * @return array<int, PlaceSuggestion>
     */
    public function placeAutocomplete(string $query, ?Point $near = null): array;

    public function reverseGeocode(Point $point): ?string;
}
