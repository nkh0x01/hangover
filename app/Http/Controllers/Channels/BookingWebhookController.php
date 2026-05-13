<?php

namespace App\Http\Controllers\Channels;

use App\Domain\Availability\Period;
use App\Domain\Channels\Data\ChannelReservationDTO;
use App\Domain\Channels\Services\ChannelReservationImportService;
use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Models\ChannelSyncLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Booking.com webhook callbacks.
 *
 * Webhook security is double-layered:
 *   1. The URL contains the connection id — wrong id = 404.
 *   2. The request body is signed with the connection's `webhook_secret`
 *      (HMAC-SHA256). We recompute and constant-time compare.
 *
 * Once verified, the body is staged via ChannelReservationImportService and
 * promoted to a real Reservation by the existing pipeline. Cancellations are
 * routed through CancelReservation. This controller never writes reservation
 * state directly.
 */
class BookingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ChannelConnection $connection,
        ChannelReservationImportService $importer,
    ): JsonResponse {
        if (! $connection->isBooking()) {
            return response()->json(['error' => 'not_a_booking_connection'], 404);
        }

        $body = $request->getContent();
        $providedSignature = (string) $request->header('X-Booking-Signature', '');
        $secret = (string) ($connection->credentials['webhook_secret'] ?? '');

        if ($secret === '' || ! $this->signatureValid($body, $providedSignature, $secret)) {
            $this->logFailed($connection, 'invalid_signature', $body);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? '');
        $reservation = $payload['reservation'] ?? [];
        $externalId = (string) ($reservation['reservation_id'] ?? '');

        if ($externalId === '') {
            $this->logFailed($connection, 'missing_reservation_id', $body);
            return response()->json(['error' => 'missing_reservation_id'], 422);
        }

        $startedAt = now();
        try {
            if ($event === 'reservation.cancelled') {
                $importer->cancelByExternalId(
                    $connection,
                    $externalId,
                    'Cancelled via Booking.com webhook',
                );
            } else {
                $dto = $this->toDTO($reservation);
                $staged = $importer->stage($connection, $dto);
                $importer->process($staged);
            }
        } catch (\Throwable $e) {
            ChannelSyncLog::create([
                'channel_connection_id' => $connection->id,
                'direction' => ChannelSyncLog::DIRECTION_IN,
                'action' => ChannelSyncLog::ACTION_WEBHOOK_RECEIVED,
                'status' => ChannelSyncLog::STATUS_FAILED,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'payload_summary' => ['event' => $event, 'external_id' => $externalId],
                'error' => $e->getMessage(),
                'triggered_by' => ChannelSyncLog::TRIGGER_WEBHOOK,
            ]);
            return response()->json(['error' => 'processing_failed'], 500);
        }

        ChannelSyncLog::create([
            'channel_connection_id' => $connection->id,
            'direction' => ChannelSyncLog::DIRECTION_IN,
            'action' => ChannelSyncLog::ACTION_WEBHOOK_RECEIVED,
            'status' => ChannelSyncLog::STATUS_SUCCESS,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'payload_summary' => ['event' => $event, 'external_id' => $externalId],
            'triggered_by' => ChannelSyncLog::TRIGGER_WEBHOOK,
        ]);

        return response()->json(['ok' => true]);
    }

    private function signatureValid(string $body, string $providedHex, string $secret): bool
    {
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $providedHex);
    }

    private function logFailed(ChannelConnection $connection, string $reason, string $body): void
    {
        ChannelSyncLog::create([
            'channel_connection_id' => $connection->id,
            'direction' => ChannelSyncLog::DIRECTION_IN,
            'action' => ChannelSyncLog::ACTION_WEBHOOK_RECEIVED,
            'status' => ChannelSyncLog::STATUS_FAILED,
            'started_at' => now(),
            'finished_at' => now(),
            'payload_summary' => ['bytes' => strlen($body)],
            'error' => $reason,
            'triggered_by' => ChannelSyncLog::TRIGGER_WEBHOOK,
        ]);
    }

    private function toDTO(array $r): ChannelReservationDTO
    {
        $guest = $r['guest'] ?? [];
        $checkIn = (string) ($r['arrival_date'] ?? CarbonImmutable::today()->toDateString());
        $checkOut = (string) ($r['departure_date'] ?? CarbonImmutable::today()->addDay()->toDateString());

        // Importer reads check_in / check_out / external_room_id from rawPayload.
        // Add the canonical keys alongside Booking's native ones so both layers
        // see what they expect.
        $rawPayload = array_merge($r, [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'external_room_id' => (string) ($r['room_id'] ?? ''),
            'guest' => array_merge($guest, [
                'first_name' => (string) ($guest['first_name'] ?? 'Booking'),
                'last_name' => (string) ($guest['last_name'] ?? 'Guest'),
            ]),
        ]);

        return new ChannelReservationDTO(
            externalId: (string) $r['reservation_id'],
            externalRoomId: (string) ($r['room_id'] ?? ''),
            period: new Period($checkIn, $checkOut),
            guestFirstName: (string) ($guest['first_name'] ?? 'Booking'),
            guestLastName: (string) ($guest['last_name'] ?? 'Guest'),
            guestEmail: $guest['email'] ?? null,
            guestPhone: $guest['phone'] ?? null,
            adults: (int) ($r['adults'] ?? 1),
            children: (int) ($r['children'] ?? 0),
            total: isset($r['total']) ? (float) $r['total'] : null,
            currency: (string) ($r['currency'] ?? 'GEL'),
            rawPayload: $rawPayload,
            externalRateId: $r['rate_id'] ?? null,
            specialRequests: $r['special_requests'] ?? null,
        );
    }
}
