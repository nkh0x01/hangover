<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Http\Controllers;

use App\Modules\Geo\Models\City;
use App\Modules\Pricing\Http\Requests\CreateEstimateRequest;
use App\Modules\Pricing\Http\Resources\FareEstimateResource;
use App\Modules\Pricing\Services\FareEstimateService;
use App\Support\Geo\Point;
use Illuminate\Routing\Controller;

final class EstimateController extends Controller
{
    public function store(CreateEstimateRequest $request, FareEstimateService $service): FareEstimateResource
    {
        $pickup = new Point((float) $request->input('pickup.lat'), (float) $request->input('pickup.lng'));
        $dropoff = new Point((float) $request->input('dropoff.lat'), (float) $request->input('dropoff.lng'));

        // City resolution: first active city for the MVP.
        $city = City::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        $estimate = $service->estimate(
            customer: $request->user(),
            city: $city,
            pickup: $pickup,
            dropoff: $dropoff,
            vehicleType: (string) ($request->input('vehicle_type') ?? 'scooter_electric'),
        );

        return new FareEstimateResource($estimate);
    }
}
