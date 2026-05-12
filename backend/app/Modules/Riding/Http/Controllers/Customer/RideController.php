<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Controllers\Customer;

use App\Modules\Riding\Actions\CancelRide;
use App\Modules\Riding\Actions\CreateRideRequest as CreateRideRequestAction;
use App\Modules\Riding\Dto\RideRequestData;
use App\Modules\Riding\Http\Requests\CancelRideRequest;
use App\Modules\Riding\Http\Requests\CreateRideRequest;
use App\Modules\Riding\Http\Resources\RideResource;
use App\Modules\Riding\Models\Ride;
use App\Support\Geo\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RideController extends Controller
{
    public function store(CreateRideRequest $request, CreateRideRequestAction $action): RideResource
    {
        $dto = new RideRequestData(
            fareEstimateUlid: (string) $request->input('fare_estimate_id'),
            pickup: new Point((float) $request->input('pickup.lat'), (float) $request->input('pickup.lng')),
            pickupAddress: (string) $request->input('pickup.address'),
            dropoff: new Point((float) $request->input('dropoff.lat'), (float) $request->input('dropoff.lng')),
            dropoffAddress: (string) $request->input('dropoff.address'),
            paymentMethod: (string) $request->input('payment_method'),
            note: $request->input('note'),
        );

        $ride = $action->execute($request->user(), $dto);

        return new RideResource($ride->load('driver'));
    }

    public function show(Request $request, string $ulid): RideResource
    {
        $ride = $this->findOwned($request, $ulid);

        return new RideResource($ride->load('driver'));
    }

    public function active(Request $request): JsonResponse|RideResource
    {
        $ride = Ride::query()
            ->where('customer_id', $request->user()->id)
            ->whereIn('status', [
                'requested', 'searching', 'offered', 'accepted',
                'driver_arriving', 'driver_arrived', 'in_progress',
            ])
            ->latest('id')
            ->first();

        if (! $ride) {
            return new JsonResponse(['data' => null]);
        }

        return new RideResource($ride->load('driver'));
    }

    public function cancel(CancelRideRequest $request, string $ulid, CancelRide $action): RideResource
    {
        $ride = $this->findOwned($request, $ulid);
        $ride = $action->execute($request->user(), $ride, (string) $request->validated('reason'));

        return new RideResource($ride->load('driver'));
    }

    public function index(Request $request): JsonResponse
    {
        $rides = Ride::query()
            ->where('customer_id', $request->user()->id)
            ->orderByDesc('requested_at')
            ->limit(50)
            ->get();

        return new JsonResponse([
            'data' => RideResource::collection($rides)->resolve(),
        ]);
    }

    private function findOwned(Request $request, string $ulid): Ride
    {
        $ride = Ride::query()->where('ulid', $ulid)->first();
        if (! $ride || $ride->customer_id !== $request->user()->id) {
            throw new HttpException(404, 'http.not_found');
        }

        return $ride;
    }
}
