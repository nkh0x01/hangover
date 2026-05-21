<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Controllers\Driver;

use App\Modules\Riding\Actions\CancelRide;
use App\Modules\Riding\Actions\CompleteTrip;
use App\Modules\Riding\Actions\DriverArrived;
use App\Modules\Riding\Actions\DriverArriving;
use App\Modules\Riding\Actions\StartTrip;
use App\Modules\Riding\Http\Requests\CancelRideRequest;
use App\Modules\Riding\Http\Resources\RideResource;
use App\Modules\Riding\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RideController extends Controller
{
    public function active(Request $request): JsonResponse|RideResource
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return new JsonResponse(['data' => null]);
        }

        $ride = Ride::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', [
                'accepted', 'driver_arriving', 'driver_arrived', 'in_progress',
            ])
            ->latest('id')
            ->first();

        if (! $ride) {
            return new JsonResponse(['data' => null]);
        }

        return new RideResource($ride->load('driver'));
    }

    public function show(Request $request, string $ulid): RideResource
    {
        $ride = $this->findAssigned($request, $ulid);

        return new RideResource($ride->load('driver'));
    }

    public function arriving(Request $request, string $ulid, DriverArriving $action): RideResource
    {
        $ride = $this->findAssigned($request, $ulid);
        $ride = $action->execute($request->user()->driver, $ride);

        return new RideResource($ride->load('driver'));
    }

    public function arrived(Request $request, string $ulid, DriverArrived $action): RideResource
    {
        $ride = $this->findAssigned($request, $ulid);
        $ride = $action->execute($request->user()->driver, $ride);

        return new RideResource($ride->load('driver'));
    }

    public function start(Request $request, string $ulid, StartTrip $action): RideResource
    {
        $ride = $this->findAssigned($request, $ulid);
        $ride = $action->execute($request->user()->driver, $ride);

        return new RideResource($ride->load('driver'));
    }

    public function complete(Request $request, string $ulid, CompleteTrip $action): RideResource
    {
        $ride = $this->findAssigned($request, $ulid);

        $finalAmount = $request->input('final_amount');
        $waiting = $request->input('waiting_seconds');

        $ride = $action->execute(
            driver: $request->user()->driver,
            ride: $ride,
            finalAmount: $finalAmount !== null ? (float) $finalAmount : null,
            waitingSeconds: $waiting !== null ? (int) $waiting : null,
        );

        return new RideResource($ride->load('driver'));
    }

    public function cancel(CancelRideRequest $request, string $ulid, CancelRide $action): RideResource
    {
        $ride = $this->findAssigned($request, $ulid);
        $ride = $action->execute($request->user(), $ride, (string) $request->validated('reason'));

        return new RideResource($ride->load('driver'));
    }

    private function findAssigned(Request $request, string $ulid): Ride
    {
        $driver = $request->user()->driver;
        $ride = Ride::query()
            ->where('ulid', $ulid)
            ->first();

        if (! $ride || ! $driver || $ride->driver_id !== $driver->id) {
            throw new HttpException(404, 'http.not_found');
        }

        return $ride;
    }
}
