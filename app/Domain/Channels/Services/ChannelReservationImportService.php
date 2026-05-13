<?php

namespace App\Domain\Channels\Services;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Channels\Data\ChannelReservationDTO;
use App\Domain\Channels\Exceptions\ChannelMappingException;
use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stages an inbound channel reservation, then attempts to promote it to a real
 * Reservation via the existing CreateReservation action — i.e. it does NOT
 * duplicate reservation logic. Three guarantees:
 *
 *   1. Idempotent on (channel_connection_id, external_id): re-importing the
 *      same external booking will not create a second Reservation.
 *   2. Same external_id with an identical hash is a no-op (returns existing
 *      row with status='duplicate' / 'processed').
 *   3. If the requested room type / period clashes with a local direct booking
 *      or a different channel booking, the staged row is flipped to
 *      status='conflict' and we never overwrite the local Reservation.
 */
class ChannelReservationImportService
{
    public function __construct(
        private readonly ChannelMappingService $mapper,
        private readonly CreateReservation $createReservation,
        private readonly CancelReservation $cancelReservation,
        private readonly AvailabilityService $availability,
    ) {
    }

    /**
     * Handle an OTA-side cancellation: locate the local Reservation for the
     * given external_id and route it through CancelReservation so the
     * availability ledger is released. Returns true if a real reservation
     * was cancelled, false if there was nothing to cancel (already cancelled,
     * never imported, etc.).
     */
    public function cancelByExternalId(ChannelConnection $connection, string $externalId, string $reason = 'Cancelled by channel'): bool
    {
        $channelRow = ChannelReservation::query()
            ->where('channel_connection_id', $connection->id)
            ->where('external_id', $externalId)
            ->first();

        if (! $channelRow || ! $channelRow->reservation_id) {
            return false;
        }

        $reservation = Reservation::query()->find($channelRow->reservation_id);
        if (! $reservation || $reservation->status === Reservation::STATUS_CANCELLED) {
            $channelRow->update([
                'status' => ChannelReservation::STATUS_PROCESSED,
                'error' => null,
                'processed_at' => now(),
            ]);
            return false;
        }

        try {
            $this->cancelReservation->execute($reservation, $reason);
        } catch (InvalidReservationState $e) {
            // Cancelling a checked-out reservation is a no-op for the channel.
            $channelRow->update([
                'status' => ChannelReservation::STATUS_FAILED,
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            return false;
        }

        $channelRow->update([
            'status' => ChannelReservation::STATUS_PROCESSED,
            'error' => null,
            'processed_at' => now(),
        ]);

        return true;
    }

    public function stage(ChannelConnection $connection, ChannelReservationDTO $dto): ChannelReservation
    {
        $hash = $dto->fingerprint();

        $existing = ChannelReservation::query()
            ->where('channel_connection_id', $connection->id)
            ->where('external_id', $dto->externalId)
            ->first();

        if ($existing) {
            // Same payload — idempotent no-op. Different payload (hash changed)
            // would be an update from the OTA side; in Phase 4 we record it
            // as 'duplicate' and require a human to reconcile, rather than
            // silently rewriting the local reservation.
            if ($existing->hash !== $hash) {
                $existing->update([
                    'raw_payload' => $dto->rawPayload,
                    'hash' => $hash,
                    'status' => ChannelReservation::STATUS_DUPLICATE,
                    'error' => 'External payload changed after initial import.',
                ]);
            }
            return $existing;
        }

        return ChannelReservation::create([
            'channel_connection_id' => $connection->id,
            'external_id' => $dto->externalId,
            'hash' => $hash,
            'raw_payload' => $dto->rawPayload,
            'status' => ChannelReservation::STATUS_RECEIVED,
            'received_at' => now(),
        ]);
    }

    /**
     * Try to turn a staged row into a real Reservation. Already-processed
     * rows are returned unchanged. Conflicts are recorded, never overwriting.
     */
    public function process(ChannelReservation $channelReservation): ChannelReservation
    {
        if (in_array($channelReservation->status, [
            ChannelReservation::STATUS_PROCESSED,
            ChannelReservation::STATUS_CONFLICT,
        ], true)) {
            return $channelReservation;
        }

        $connection = $channelReservation->connection()->firstOrFail();
        $payload = $channelReservation->raw_payload ?? [];

        try {
            $roomType = $this->mapper->roomTypeForExternal(
                $connection,
                (string) ($payload['external_room_id'] ?? ''),
            );
        } catch (ChannelMappingException $e) {
            $channelReservation->update([
                'status' => ChannelReservation::STATUS_FAILED,
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            return $channelReservation->refresh();
        }

        try {
            $reservation = DB::transaction(function () use ($connection, $payload, $roomType, $channelReservation): Reservation {
                $guest = $this->upsertGuest($connection, $payload);

                $data = new CreateReservationData(
                    property: $connection->property()->firstOrFail(),
                    guest: $guest,
                    roomType: $roomType,
                    period: new \App\Domain\Availability\Period(
                        (string) $payload['check_in'],
                        (string) $payload['check_out'],
                    ),
                    adults: (int) ($payload['adults'] ?? 1),
                    children: (int) ($payload['children'] ?? 0),
                    source: $connection->channel,
                    externalReference: $channelReservation->external_id,
                    initialStatus: Reservation::STATUS_CONFIRMED,
                );

                return $this->createReservation->execute($data);
            });
        } catch (RoomNotAvailable $e) {
            $channelReservation->update([
                'status' => ChannelReservation::STATUS_CONFLICT,
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            return $channelReservation->refresh();
        } catch (Throwable $e) {
            Log::warning('Channel reservation promotion failed', [
                'channel_reservation_id' => $channelReservation->id,
                'error' => $e->getMessage(),
            ]);
            $channelReservation->update([
                'status' => ChannelReservation::STATUS_FAILED,
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            return $channelReservation->refresh();
        }

        $channelReservation->update([
            'reservation_id' => $reservation->id,
            'status' => ChannelReservation::STATUS_PROCESSED,
            'error' => null,
            'processed_at' => now(),
        ]);

        return $channelReservation->refresh();
    }

    private function upsertGuest(ChannelConnection $connection, array $payload): Guest
    {
        $guest = $payload['guest'] ?? [];
        $email = $guest['email'] ?? null;
        $first = (string) ($guest['first_name'] ?? 'Channel');
        $last  = (string) ($guest['last_name']  ?? 'Guest');

        if ($email) {
            $existing = Guest::query()
                ->where('property_id', $connection->property_id)
                ->where('email', $email)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return Guest::create([
            'property_id' => $connection->property_id,
            'first_name'  => $first,
            'last_name'   => $last,
            'email'       => $email,
            'phone'       => $guest['phone'] ?? null,
            'country'     => $guest['country'] ?? null,
        ]);
    }
}
