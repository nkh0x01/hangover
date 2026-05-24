<?php

declare(strict_types=1);

namespace App\Modules\Driver\Http\Controllers;

use App\Modules\Driver\Actions\SetDriverOffline;
use App\Modules\Driver\Actions\SetDriverOnline;
use App\Modules\Driver\Http\Requests\SetOnlineRequest;
use App\Support\Exceptions\DomainException;
use App\Support\Geo\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class StatusController extends Controller
{
    public function online(SetOnlineRequest $request, SetDriverOnline $action): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            throw $this->driverNotFound();
        }

        $vehicleId = $request->input('vehicle_id');
        $driver = $action->execute(
            driver: $driver,
            location: new Point((float) $request->validated('lat'), (float) $request->validated('lng')),
            vehicleId: $vehicleId !== null ? (int) $vehicleId : null,
        );

        return new JsonResponse([
            'data' => [
                'online' => $driver->online,
                'online_since' => $driver->online_since?->toIso8601String(),
            ],
        ]);
    }

    public function offline(Request $request, SetDriverOffline $action): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            throw $this->driverNotFound();
        }

        $action->execute($driver);

        return new JsonResponse(['data' => ['online' => false]]);
    }

    private function driverNotFound(): DomainException
    {
        return new class('Driver profile not found.') extends DomainException
        {
            public function code(): string
            {
                return 'driver.not_found';
            }

            public function status(): int
            {
                return 404;
            }
        };
    }
}
