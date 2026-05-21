<?php

declare(strict_types=1);

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Riding\Models\Ride;
use App\Modules\Support\Actions\RaiseSosEvent;
use App\Support\Geo\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /api/v1/safety/sos`
 *
 * The mobile clients (customer + driver) hit this when the user
 * triggers the in-ride safety button. Always returns 201 with the
 * SOS-event id; the server is responsible for routing the alert.
 *
 * Auth: any authenticated user. Rate-limited at the route layer.
 */
final class SosController
{
    public function __construct(private readonly RaiseSosEvent $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ride_ulid' => ['nullable', 'string', 'size:26'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        $ride = isset($data['ride_ulid'])
            ? Ride::query()->where('ulid', $data['ride_ulid'])->first()
            : null;

        $point = isset($data['lat'], $data['lng'])
            ? new Point((float) $data['lat'], (float) $data['lng'])
            : null;

        $event = $this->action->execute(
            user: $request->user(),
            ride: $ride,
            location: $point,
            body: $data['body'] ?? null,
        );

        return new JsonResponse([
            'data' => [
                'id' => $event->id,
                'status' => $event->status,
                'created_at' => $event->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
