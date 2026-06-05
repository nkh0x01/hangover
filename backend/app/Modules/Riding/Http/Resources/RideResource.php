<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Resources;

use App\Modules\Riding\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ride
 */
final class RideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $coords = $this->coordinates();

        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'pickup' => [
                'address' => $this->pickup_address,
                'lat' => $coords['pickup_lat'],
                'lng' => $coords['pickup_lng'],
            ],
            'dropoff' => [
                'address' => $this->dropoff_address,
                'lat' => $coords['dropoff_lat'],
                'lng' => $coords['dropoff_lng'],
            ],
            'fare' => [
                'quoted' => (float) $this->quoted_amount,
                'final' => $this->final_amount !== null ? (float) $this->final_amount : null,
                'currency' => $this->currency,
                'surge_multiplier' => (float) $this->surge_multiplier,
            ],
            'payment_method' => $this->payment_method,
            'driver' => $this->whenLoaded('driver', function () {
                $driver = $this->driver;
                $driver?->loadMissing('user', 'currentVehicle');

                return $driver ? [
                    'id' => $driver->user->ulid,
                    'name' => trim(($driver->user->first_name ?? '').' '.($driver->user->last_name ?? '')) ?: null,
                    'phone' => $driver->user->phone_e164,
                    'rating_avg' => (float) $driver->rating_avg,
                    'vehicle' => $driver->currentVehicle ? [
                        'brand' => $driver->currentVehicle->brand,
                        'model' => $driver->currentVehicle->model,
                        'plate' => $driver->currentVehicle->plate,
                        'color' => $driver->currentVehicle->color,
                    ] : null,
                ] : null;
            }),
            'timestamps' => [
                'requested_at' => $this->requested_at?->toIso8601String(),
                'accepted_at' => $this->accepted_at?->toIso8601String(),
                'arriving_at' => $this->arriving_at?->toIso8601String(),
                'arrived_at' => $this->arrived_at?->toIso8601String(),
                'started_at' => $this->started_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ],
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }

    /**
     * @return array{pickup_lat: float, pickup_lng: float, dropoff_lat: float, dropoff_lng: float}
     */
    private function coordinates(): array
    {
        return $this->resource->mapCoordinates();
    }
}
