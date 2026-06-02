<?php

declare(strict_types=1);

namespace App\Modules\Geo\Http\Controllers;

use App\Modules\Geo\Models\City;
use App\Modules\Geo\Services\DbNearbyDriverFinder;
use App\Modules\Geo\Services\NearbyDriverIndex;
use App\Support\Geo\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class NearbyDriversController extends Controller
{
    public function __invoke(Request $request, NearbyDriverIndex $index, DbNearbyDriverFinder $dbFallback): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'between:0.1,10'],
        ]);

        $city = City::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        $center = new Point((float) $request->query('lat'), (float) $request->query('lng'));
        $radiusKm = (float) $request->query('radius_km', 3.0);
        $rows = $index->nearby(cityId: $city->id, center: $center, radiusKm: $radiusKm, limit: 20);
        $rows = $rows !== [] ? $rows : $dbFallback->nearby($city->id, $center, $radiusKm, 20);

        // Strip the PII — we expose only positions, not driver identities.
        return new JsonResponse([
            'data' => array_map(
                fn (array $row): array => [
                    'lat' => $row['lat'],
                    'lng' => $row['lng'],
                ],
                $rows,
            ),
        ]);
    }
}
