<?php

namespace App\Domain\Channels\Services;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Data\AvailabilityDTO;
use App\Domain\Channels\Data\RateDTO;
use App\Domain\Channels\Data\RestrictionDTO;
use App\Domain\Channels\Data\SyncResult;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Domain\Channels\Support\ProviderRegistry;
use App\Domain\Pricing\PricingService;
use App\Models\AvailabilityCalendar;
use App\Models\ChannelConnection;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\ChannelSyncLog;
use App\Models\DailyRoomPrice;
use App\Models\User;
use App\Notifications\ChannelSyncFailed;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Orchestrates every sync between us and a channel provider. The provider
 * implementation handles the wire format; this service handles the local-side
 * concerns shared by every channel: building DTOs from our own services
 * (availability ledger, pricing engine, daily restrictions), running the log
 * lifecycle, updating connection health, and routing inbound reservations
 * through the import service.
 *
 * Important: this service never duplicates reservation logic — promotion
 * is delegated to ChannelReservationImportService → CreateReservation.
 */
class ChannelSyncService
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly ChannelMappingService $mapper,
        private readonly ChannelReservationImportService $importer,
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {
    }

    public function testConnection(ChannelConnection $connection, string $triggeredBy = ChannelSyncLog::TRIGGER_MANUAL): SyncResult
    {
        return $this->runLogged(
            $connection,
            ChannelSyncLog::DIRECTION_OUT,
            ChannelSyncLog::ACTION_TEST_CONNECTION,
            $triggeredBy,
            function (ChannelProviderInterface $provider) use ($connection): SyncResult {
                $ok = $provider->testConnection($connection);
                return $ok
                    ? SyncResult::success(1, [['result' => 'ok']])
                    : SyncResult::failure('Provider reported unhealthy.');
            },
        );
    }

    public function pullReservations(ChannelConnection $connection, Period $window, string $triggeredBy = ChannelSyncLog::TRIGGER_MANUAL): SyncResult
    {
        return $this->runLogged(
            $connection,
            ChannelSyncLog::DIRECTION_IN,
            ChannelSyncLog::ACTION_PULL_RESERVATIONS,
            $triggeredBy,
            function (ChannelProviderInterface $provider) use ($connection, $window): SyncResult {
                if (! $provider->supportsPullReservations()) {
                    return SyncResult::failure("Provider {$provider->key()} does not support pull_reservations.");
                }

                $processed = 0;
                $failed = 0;
                $items = [];

                foreach ($provider->pullReservations($connection, $window) as $dto) {
                    $staged = $this->importer->stage($connection, $dto);
                    $staged = $this->importer->process($staged);

                    $items[] = [
                        'external_id' => $dto->externalId,
                        'status' => $staged->status,
                        'reservation_id' => $staged->reservation_id,
                    ];

                    in_array($staged->status, ['processed'], true)
                        ? $processed++
                        : ($staged->status === 'conflict' || $staged->status === 'failed' ? $failed++ : $processed++);
                }

                // OTA cancellations come down the same pull. Each external_id
                // is routed through CancelReservation so the availability
                // ledger gets released — no logic duplicated locally.
                foreach ($provider->pullCancellations($connection, $window) as $externalId) {
                    $cancelled = $this->importer->cancelByExternalId($connection, $externalId);
                    $items[] = [
                        'external_id' => $externalId,
                        'action' => 'cancel',
                        'cancelled' => $cancelled,
                    ];
                    $processed++;
                }

                $connection->update(['last_pull_at' => now()]);

                return $failed > 0
                    ? SyncResult::partial($processed, $failed, $items)
                    : SyncResult::success($processed, $items);
            },
        );
    }

    public function pushAvailability(ChannelConnection $connection, Period $window, string $triggeredBy = ChannelSyncLog::TRIGGER_MANUAL): SyncResult
    {
        return $this->runLogged(
            $connection,
            ChannelSyncLog::DIRECTION_OUT,
            ChannelSyncLog::ACTION_PUSH_AVAILABILITY,
            $triggeredBy,
            function (ChannelProviderInterface $provider) use ($connection, $window): SyncResult {
                if (! $provider->supportsPushAvailability()) {
                    return SyncResult::failure("Provider {$provider->key()} does not support push_availability.");
                }

                $rows = $this->buildAvailabilityRows($connection, $window);
                $provider->pushAvailability($connection, $rows);
                $connection->update(['last_push_at' => now()]);

                return SyncResult::success(count($rows), array_map(fn (AvailabilityDTO $r) => $r->toArray(), $rows));
            },
        );
    }

    public function pushRates(ChannelConnection $connection, Period $window, string $triggeredBy = ChannelSyncLog::TRIGGER_MANUAL): SyncResult
    {
        return $this->runLogged(
            $connection,
            ChannelSyncLog::DIRECTION_OUT,
            ChannelSyncLog::ACTION_PUSH_RATES,
            $triggeredBy,
            function (ChannelProviderInterface $provider) use ($connection, $window): SyncResult {
                if (! $provider->supportsPushRates()) {
                    return SyncResult::failure("Provider {$provider->key()} does not support push_rates.");
                }

                $rows = $this->buildRateRows($connection, $window);
                $provider->pushRates($connection, $rows);
                $connection->update(['last_push_at' => now()]);

                return SyncResult::success(count($rows), array_map(fn (RateDTO $r) => $r->toArray(), $rows));
            },
        );
    }

    public function pushRestrictions(ChannelConnection $connection, Period $window, string $triggeredBy = ChannelSyncLog::TRIGGER_MANUAL): SyncResult
    {
        return $this->runLogged(
            $connection,
            ChannelSyncLog::DIRECTION_OUT,
            ChannelSyncLog::ACTION_PUSH_RESTRICTIONS,
            $triggeredBy,
            function (ChannelProviderInterface $provider) use ($connection, $window): SyncResult {
                if (! $provider->supportsPushRestrictions()) {
                    return SyncResult::failure("Provider {$provider->key()} does not support push_restrictions.");
                }

                $rows = $this->buildRestrictionRows($connection, $window);
                $provider->pushRestrictions($connection, $rows);
                $connection->update(['last_push_at' => now()]);

                return SyncResult::success(count($rows), array_map(fn (RestrictionDTO $r) => $r->toArray(), $rows));
            },
        );
    }

    /**
     * Pull room-nights from the local availability ledger and translate
     * room_type_id → external_room_id via mappings. We count how many rooms
     * of each mapped type are still STATUS_OPEN on each night.
     *
     * @return array<int, AvailabilityDTO>
     */
    private function buildAvailabilityRows(ChannelConnection $connection, Period $window): array
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $connection->id)->get();
        if ($mappings->isEmpty()) {
            return [];
        }

        $property = $connection->property()->firstOrFail();
        $matrix = $this->availability->matrix($property, $window);

        $byRoomTypeNight = [];
        foreach ($property->rooms()->get() as $room) {
            foreach ($window->nightDates() as $date) {
                $row = $matrix[$room->id][$date] ?? null;
                if (! $row) {
                    continue;
                }
                $isOpen = $row->status === AvailabilityCalendar::STATUS_OPEN;
                $byRoomTypeNight[$room->room_type_id][$date]
                    = ($byRoomTypeNight[$room->room_type_id][$date] ?? 0) + ($isOpen ? 1 : 0);
            }
        }

        $rows = [];
        foreach ($mappings as $mapping) {
            $perNight = $byRoomTypeNight[$mapping->room_type_id] ?? [];
            foreach ($window->nightDates() as $date) {
                $rows[] = new AvailabilityDTO(
                    externalRoomId: (string) $mapping->external_room_id,
                    date: $date,
                    available: (int) ($perNight[$date] ?? 0),
                );
            }
        }

        return $rows;
    }

    /**
     * Build rate rows from the pricing engine. We ask the engine for a price
     * per (room_type, night), apply any per-mapping markup, and emit one row
     * per (external_room_id, date) pair.
     *
     * @return array<int, RateDTO>
     */
    private function buildRateRows(ChannelConnection $connection, Period $window): array
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $connection->id)->get();
        if ($mappings->isEmpty()) {
            return [];
        }

        $rateByRoomType = ChannelRateMapping::where('channel_connection_id', $connection->id)
            ->get()
            ->keyBy('room_type_id');

        $rows = [];
        foreach ($mappings as $mapping) {
            $roomType = $mapping->roomType()->first();
            if (! $roomType) {
                continue;
            }
            $rateMap = $rateByRoomType->get($mapping->room_type_id);

            $quote = $this->pricing->priceForStay($roomType, $window);
            foreach ($quote->nights as $night) {
                $rows[] = new RateDTO(
                    externalRoomId: (string) $mapping->external_room_id,
                    externalRateId: $rateMap?->external_rate_id,
                    date: $night->date->toDateString(),
                    amount: $this->mapper->applyMarkup($night->amount, $rateMap),
                    currency: $night->currency,
                );
            }
        }

        return $rows;
    }

    /**
     * Translate daily_room_prices restrictions into per-(external_room_id, date)
     * DTOs. Only rooms with a mapping for this connection are emitted.
     *
     * @return array<int, RestrictionDTO>
     */
    private function buildRestrictionRows(ChannelConnection $connection, Period $window): array
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $connection->id)->get();
        if ($mappings->isEmpty()) {
            return [];
        }

        $roomTypeIds = $mappings->pluck('room_type_id')->unique()->all();

        $byRoomTypeDate = DailyRoomPrice::query()
            ->whereIn('room_type_id', $roomTypeIds)
            ->whereIn('date', $window->nightDates())
            ->whereNull('room_id')
            ->get()
            ->keyBy(fn ($r) => $r->room_type_id.'|'.$r->date->toDateString());

        $rows = [];
        foreach ($mappings as $mapping) {
            foreach ($window->nightDates() as $date) {
                $key = $mapping->room_type_id.'|'.$date;
                $r = $byRoomTypeDate->get($key);
                $rows[] = new RestrictionDTO(
                    externalRoomId: (string) $mapping->external_room_id,
                    date: $date,
                    minStay: $r?->min_stay,
                    maxStay: $r?->max_stay,
                    closedToArrival: (bool) ($r?->closed_to_arrival ?? false),
                    closedToDeparture: (bool) ($r?->closed_to_departure ?? false),
                    stopSell: false,
                );
            }
        }

        return $rows;
    }

    /**
     * Run a provider call with full lifecycle bookkeeping: start log,
     * resolve provider, dispatch, capture timing, log result, update
     * health counters on the connection.
     *
     * @param  callable(ChannelProviderInterface):SyncResult  $fn
     */
    private function runLogged(
        ChannelConnection $connection,
        string $direction,
        string $action,
        string $triggeredBy,
        callable $fn,
    ): SyncResult {
        $startedAt = now();
        $startMs = microtime(true);
        $log = ChannelSyncLog::create([
            'channel_connection_id' => $connection->id,
            'direction' => $direction,
            'action' => $action,
            'status' => ChannelSyncLog::STATUS_SUCCESS, // optimistic; rewritten below
            'started_at' => $startedAt,
            'triggered_by' => $triggeredBy,
            'payload_summary' => null,
            'response_summary' => null,
        ]);

        try {
            $provider = $this->providers->forConnection($connection);
            $result = $fn($provider);

            $log->update([
                'status' => $result->status(),
                'response_summary' => $result->summary(),
                'error' => $result->error,
                'duration_ms' => (int) round((microtime(true) - $startMs) * 1000),
                'finished_at' => now(),
            ]);

            if ($result->ok) {
                if ($connection->error_count > 0 || $connection->last_error) {
                    $connection->update(['last_error' => null, 'error_count' => 0]);
                }
            } else {
                $this->recordFailure($connection, $result->error ?? 'Sync failed.');
            }

            return $result;
        } catch (ChannelProviderException $e) {
            $log->update([
                'status' => ChannelSyncLog::STATUS_FAILED,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startMs) * 1000),
                'finished_at' => now(),
            ]);
            $this->recordFailure($connection, $e->getMessage());
            return SyncResult::failure($e->getMessage());
        } catch (Throwable $e) {
            $log->update([
                'status' => ChannelSyncLog::STATUS_FAILED,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startMs) * 1000),
                'finished_at' => now(),
            ]);
            $this->recordFailure($connection, $e->getMessage());
            return SyncResult::failure($e->getMessage());
        }
    }

    private function recordFailure(ChannelConnection $connection, string $error): void
    {
        $previousCount = (int) $connection->error_count;
        $newCount = $previousCount + 1;

        $connection->update([
            'last_error' => $error,
            'error_count' => $newCount,
            'status' => $newCount >= 5
                ? ChannelConnection::STATUS_ERROR
                : $connection->status,
        ]);

        // Only fire the notification on the threshold crossing — not on every
        // subsequent failure — so a single broken provider doesn't flood the
        // duty manager's inbox.
        if ($previousCount < ChannelSyncFailed::FAILURE_THRESHOLD
            && $newCount >= ChannelSyncFailed::FAILURE_THRESHOLD
        ) {
            $managers = User::query()
                ->where('property_id', $connection->property_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'manager']))
                ->get();

            if ($managers->isNotEmpty()) {
                Notification::send($managers, ChannelSyncFailed::from($connection->fresh(), $error));
            }
        }
    }
}
