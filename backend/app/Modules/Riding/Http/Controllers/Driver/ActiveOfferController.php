<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Controllers\Driver;

use App\Modules\Riding\Models\RideOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Returns the currently-pending offer for the authenticated driver, if
 * any. The customer/driver apps consume this as a poll-based fallback
 * when the WebSocket isn't connected — the canonical path remains the
 * `ride.offered` event on `private-driver.{ulid}`.
 */
final class ActiveOfferController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $driver = $request->user()?->driver;
        if (! $driver) {
            return new JsonResponse(['data' => null]);
        }

        $offer = RideOffer::query()
            ->with('ride:id,ulid,pickup_address,dropoff_address,quoted_amount,currency')
            ->where('driver_id', $driver->id)
            ->where('response', 'pending')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $offer) {
            return new JsonResponse(['data' => null]);
        }

        return new JsonResponse([
            'data' => [
                'ride_ulid' => $offer->ride->ulid,
                'expires_at' => $offer->expires_at->toIso8601String(),
                'pickup' => ['address' => $offer->ride->pickup_address],
                'dropoff' => ['address' => $offer->ride->dropoff_address],
                'distance_to_pickup_m' => $offer->distance_to_pickup_m,
                'fare' => [
                    'amount' => (float) $offer->ride->quoted_amount,
                    'currency' => $offer->ride->currency,
                ],
            ],
        ]);
    }
}
