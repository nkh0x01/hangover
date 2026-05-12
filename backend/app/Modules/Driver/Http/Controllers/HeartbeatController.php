<?php

declare(strict_types=1);

namespace App\Modules\Driver\Http\Controllers;

use App\Modules\Driver\Actions\IngestLocationHeartbeat;
use App\Modules\Driver\Http\Requests\HeartbeatRequest;
use App\Support\Geo\Point;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class HeartbeatController extends Controller
{
    public function __invoke(HeartbeatRequest $request, IngestLocationHeartbeat $action): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            throw new HttpException(404, 'driver.not_found');
        }

        $recordedAt = $request->validated('recorded_at');

        $action->execute(
            driver: $driver,
            location: new Point((float) $request->validated('lat'), (float) $request->validated('lng')),
            heading: (int) ($request->validated('heading') ?? 0),
            speedKmh: (float) ($request->validated('speed_kmh') ?? 0),
            accuracyM: $request->validated('accuracy_m') !== null ? (float) $request->validated('accuracy_m') : null,
            batteryPct: $request->validated('battery_pct') !== null ? (int) $request->validated('battery_pct') : null,
            recordedAt: $recordedAt ? CarbonImmutable::parse($recordedAt) : null,
        );

        return new JsonResponse(['data' => ['ok' => true]]);
    }
}
