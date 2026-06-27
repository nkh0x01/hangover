<?php

declare(strict_types=1);

namespace App\Modules\Support\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Riding\Models\Ride;
use App\Modules\Support\Listeners\NotifyOpsOfSos;
use App\Modules\Support\Models\SosEvent;
use App\Support\Geo\Point;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer- or driver-initiated emergency. Creates a `sos_events`
 * row in `open` state, persists location, and audit-logs a P0
 * event so the on-call ops + SRE channels both see it.
 *
 * Spatial column (`location` POINT SRID 4326) is only present on
 * MySQL — handled via raw UPDATE when the driver is mysql so the
 * SQLite test suite stays portable.
 *
 * Side effects (out of band): the queued
 * {@see NotifyOpsOfSos} listener
 * pushes to the dispatcher SMS group + opens a high-priority
 * support ticket linked to the SOS event.
 */
final class RaiseSosEvent
{
    public function execute(User $user, ?Ride $ride, ?Point $location, ?string $body = null): SosEvent
    {
        $event = DB::transaction(function () use ($user, $ride, $location, $body): SosEvent {
            $event = new SosEvent([
                'user_id' => $user->id,
                'ride_id' => $ride?->id,
                'body' => $body,
                'status' => 'open',
            ]);

            // `location` is a MySQL-only spatial column (POINT NOT NULL with a
            // SPATIAL INDEX), so it must be set at insert time rather than via a
            // post-insert UPDATE, which would fail the NOT NULL check first.
            if (DB::getDriverName() === 'mysql' && $location !== null) {
                $event->setAttribute(
                    'location',
                    DB::raw(sprintf("ST_GeomFromText('POINT(%F %F)', 4326)", $location->lng, $location->lat)),
                );
            }

            $event->save();

            return $event->fresh() ?? $event;
        });

        Log::channel('security')->critical('sos.raised', [
            'sos_event_id' => $event->id,
            'user_id' => $user->id,
            'ride_id' => $ride?->id,
            'location' => $location !== null ? ['lat' => $location->lat, 'lng' => $location->lng] : null,
        ]);

        activity('safety')
            ->causedBy($user)
            ->performedOn($event)
            ->withProperties([
                'event' => 'sos.raised',
                'ride_id' => $ride?->id,
                'location' => $location !== null ? ['lat' => $location->lat, 'lng' => $location->lng] : null,
            ])
            ->log('sos.raised');

        return $event;
    }

    public function acknowledge(SosEvent $event, User $by): SosEvent
    {
        $event->update([
            'status' => 'acknowledged',
            'acknowledged_by_user_id' => $by->id,
            'acknowledged_at' => now(),
        ]);

        activity('safety')
            ->causedBy($by)
            ->performedOn($event)
            ->withProperties(['event' => 'sos.acknowledged'])
            ->log('sos.acknowledged');

        return $event->refresh();
    }

    public function resolve(SosEvent $event, User $by, string $resolution): SosEvent
    {
        $event->update([
            'status' => $resolution === 'false_alarm' ? 'false_alarm' : 'resolved',
            'resolved_at' => now(),
        ]);

        activity('safety')
            ->causedBy($by)
            ->performedOn($event)
            ->withProperties(['event' => 'sos.resolved', 'resolution' => $resolution])
            ->log('sos.resolved');

        return $event->refresh();
    }
}
