<?php

declare(strict_types=1);

namespace App\Modules\Riding\Services;

use App\Modules\Riding\Events\RideStatusChanged;
use App\Modules\Riding\Models\Ride;
use App\Modules\Riding\Models\RideStatusLog;
use App\Modules\Riding\StateMachine\RideStatus;
use App\Modules\Riding\StateMachine\Transitions;
use App\Support\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative ride status transition gate. All status changes — from
 * any Action, any Job, any admin command — must go through transition().
 */
final class RideStateMachine
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function transition(
        Ride $ride,
        RideStatus $to,
        string $actorType = 'system',
        ?int $actorId = null,
        ?string $reason = null,
        array $payload = [],
    ): Ride {
        return DB::transaction(function () use ($ride, $to, $actorType, $actorId, $reason, $payload): Ride {
            /** @var Ride $locked */
            $locked = Ride::query()->whereKey($ride->id)->lockForUpdate()->firstOrFail();

            $from = $locked->status;

            if (! Transitions::isAllowed($from, $to)) {
                throw new class("Illegal transition {$from->value} -> {$to->value}") extends DomainException
                {
                    public function code(): string
                    {
                        return 'ride.invalid_transition';
                    }
                };
            }

            $locked->status = $to;
            $this->stampTimestamps($locked, $to);
            $locked->save();

            RideStatusLog::create([
                'ride_id' => $locked->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'reason' => $reason,
                'payload' => $payload ?: null,
                'occurred_at' => now(),
            ]);

            RideStatusChanged::dispatch($locked->ulid, $from, $to, $actorType, $actorId);

            return $locked;
        });
    }

    private function stampTimestamps(Ride $ride, RideStatus $to): void
    {
        match ($to) {
            RideStatus::Accepted => $ride->accepted_at = now(),
            RideStatus::DriverArriving => $ride->arriving_at = now(),
            RideStatus::DriverArrived => $ride->arrived_at = now(),
            RideStatus::InProgress => $ride->started_at = now(),
            RideStatus::Completed => $ride->completed_at = now(),
            RideStatus::Cancelled, RideStatus::NoDrivers, RideStatus::Failed => $ride->cancelled_at = now(),
            default => null,
        };
    }
}
