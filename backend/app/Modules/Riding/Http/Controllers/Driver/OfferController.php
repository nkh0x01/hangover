<?php

declare(strict_types=1);

namespace App\Modules\Riding\Http\Controllers\Driver;

use App\Modules\Riding\Actions\AcceptRideOffer;
use App\Modules\Riding\Actions\RejectRideOffer;
use App\Modules\Riding\Http\Resources\RideResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OfferController extends Controller
{
    public function accept(Request $request, string $ulid, AcceptRideOffer $action): RideResource
    {
        $driver = $request->user()->driver()->firstOrFail();
        if ($driver->status !== 'approved') {
            throw new HttpException(403, 'driver.not_approved');
        }

        $ride = $action->execute($driver, $ulid);

        return new RideResource($ride->load('driver'));
    }

    public function reject(Request $request, string $ulid, RejectRideOffer $action): JsonResponse
    {
        $driver = $request->user()->driver()->firstOrFail();
        $action->execute($driver, $ulid);

        return new JsonResponse(['data' => ['rejected' => true]]);
    }
}
