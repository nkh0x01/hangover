<?php

namespace App\Domain\Channels\Providers;

use App\Domain\Availability\Period;
use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Data\ChannelReservationDTO;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Domain\Channels\Providers\Booking\BookingPayloadBuilder;
use App\Models\ChannelConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase 5 Booking.com adapter.
 *
 * Three execution modes, controlled per-connection:
 *
 *  - dry_run = true   (DEFAULT, SAFE): builds the payload, writes it to the
 *                     sync log, and returns synthetic success. NEVER issues
 *                     an outbound HTTP call. Use this for staging, demos,
 *                     and any environment where you don't have signed Booking
 *                     credentials.
 *  - dry_run = false, sandbox endpoint: hits Booking's sandbox API. Requires
 *                     valid sandbox credentials.
 *  - dry_run = false, production endpoint: hits the live Booking API. Each
 *                     outbound push also requires connection.live_confirmed_at
 *                     to be within the last 60 seconds — the UI sets that
 *                     when the operator explicitly confirms "yes, push live".
 *
 * The provider NEVER imports reservations on its own — promotion is done by
 * ChannelReservationImportService, which goes through CreateReservation /
 * CancelReservation. Same for availability / rates / restrictions: this
 * adapter is purely the transport layer.
 */
class BookingComService implements ChannelProviderInterface
{
    public const SANDBOX_BASE = 'https://supply-xml.booking.com/sandbox';

    public const PRODUCTION_BASE = 'https://supply-xml.booking.com';

    public function __construct(
        private readonly BookingPayloadBuilder $payloads,
    ) {
    }

    public function key(): string
    {
        return ChannelConnection::CHANNEL_BOOKING;
    }

    public function supportsPullReservations(): bool
    {
        return true;
    }

    public function supportsPushAvailability(): bool
    {
        return true;
    }

    public function supportsPushRates(): bool
    {
        return true;
    }

    public function supportsPushRestrictions(): bool
    {
        return true;
    }

    public function testConnection(ChannelConnection $connection): bool
    {
        if ($connection->isDryRun()) {
            Log::info('Booking.com testConnection (dry-run, no HTTP)', [
                'connection_id' => $connection->id,
            ]);
            return true;
        }

        try {
            $response = $this->http($connection)->get($this->endpoint($connection, '/ping'));
            return $response->successful();
        } catch (Throwable $e) {
            throw new ChannelProviderException('Booking.com test_connection failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function pullReservations(ChannelConnection $connection, Period $window): iterable
    {
        if ($connection->isDryRun()) {
            // No outbound HTTP and no synthetic reservations either — the
            // operator should use the Mock provider for pull testing.
            return [];
        }

        try {
            $query = $this->payloads->pullQuery(
                $connection,
                $window->checkIn->toDateString(),
                $window->checkOut->toDateString(),
            );
            $response = $this->http($connection)->get($this->endpoint($connection, '/reservations'), $query);
            $response->throw();
        } catch (Throwable $e) {
            throw new ChannelProviderException('Booking.com pullReservations failed: '.$e->getMessage(), 0, $e);
        }

        foreach ((array) $response->json('reservations', []) as $r) {
            if (($r['status'] ?? 'booked') === 'cancelled') {
                continue;
            }
            yield $this->toDTO($r);
        }
    }

    public function pullCancellations(ChannelConnection $connection, Period $window): iterable
    {
        if ($connection->isDryRun()) {
            return [];
        }

        try {
            $query = $this->payloads->pullQuery(
                $connection,
                $window->checkIn->toDateString(),
                $window->checkOut->toDateString(),
            );
            $response = $this->http($connection)->get($this->endpoint($connection, '/reservations'), $query);
            $response->throw();
        } catch (Throwable $e) {
            throw new ChannelProviderException('Booking.com pullCancellations failed: '.$e->getMessage(), 0, $e);
        }

        foreach ((array) $response->json('reservations', []) as $r) {
            if (($r['status'] ?? null) === 'cancelled' && ! empty($r['reservation_id'])) {
                yield (string) $r['reservation_id'];
            }
        }
    }

    public function pushAvailability(ChannelConnection $connection, array $rows): void
    {
        $payload = $this->payloads->availability($connection, $rows);
        $this->maybeDispatch($connection, '/availability', $payload, 'pushAvailability');
    }

    public function pushRates(ChannelConnection $connection, array $rows): void
    {
        $payload = $this->payloads->rates($connection, $rows);
        $this->maybeDispatch($connection, '/rates', $payload, 'pushRates');
    }

    public function pushRestrictions(ChannelConnection $connection, array $rows): void
    {
        $payload = $this->payloads->restrictions($connection, $rows);
        $this->maybeDispatch($connection, '/restrictions', $payload, 'pushRestrictions');
    }

    /**
     * Single chokepoint for every outbound push. Honours dry-run, enforces
     * the live-confirmation window, and shields the rest of the codebase
     * from HTTP details.
     */
    private function maybeDispatch(ChannelConnection $connection, string $path, array $payload, string $opLabel): void
    {
        if ($connection->isDryRun()) {
            Log::info("Booking.com {$opLabel} (dry-run, payload NOT sent)", [
                'connection_id' => $connection->id,
                'path' => $path,
                'rows' => count($payload['availability'] ?? $payload['rates'] ?? $payload['restrictions'] ?? []),
            ]);
            return;
        }

        $this->guardLivePush($connection, $opLabel);

        try {
            $this->http($connection)
                ->post($this->endpoint($connection, $path), $payload)
                ->throw();
        } catch (Throwable $e) {
            throw new ChannelProviderException("Booking.com {$opLabel} failed: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * In non-dry-run mode, the UI must have explicitly confirmed within the
     * last minute. Anything older and we refuse the push — confirmations
     * are per-action, not "session-wide".
     */
    private function guardLivePush(ChannelConnection $connection, string $opLabel): void
    {
        if (! $connection->liveConfirmationActive()) {
            throw new ChannelProviderException(
                "Live push '{$opLabel}' to Booking.com requires fresh explicit confirmation. "
                ."Use the UI 'Confirm live push' flow or set live_confirmed_at within 60 seconds.",
            );
        }
    }

    /**
     * Booking expects Basic auth with hotel_id + secret. We also include a
     * connection-scoped User-Agent so support can trace requests back to a
     * specific connection if needed.
     */
    private function http(ChannelConnection $connection): PendingRequest
    {
        $creds = $connection->credentials ?? [];
        $username = (string) ($creds['hotel_id'] ?? '');
        $password = (string) ($creds['secret'] ?? '');

        if ($username === '' || $password === '') {
            throw new ChannelProviderException('Booking.com credentials (hotel_id + secret) are not configured.');
        }

        return Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'User-Agent' => 'Hangover-PMS/1.0 (connection='.$connection->id.')',
            ])
            ->timeout(20)
            ->connectTimeout(5);
    }

    private function endpoint(ChannelConnection $connection, string $path): string
    {
        $settings = $connection->settings ?? [];
        $sandbox = (bool) ($settings['sandbox'] ?? true);
        $base = $sandbox ? self::SANDBOX_BASE : self::PRODUCTION_BASE;

        return $base.$path;
    }

    private function toDTO(array $r): ChannelReservationDTO
    {
        $guest = $r['guest'] ?? [];
        $checkIn = (string) ($r['arrival_date'] ?? '');
        $checkOut = (string) ($r['departure_date'] ?? '');

        // Importer reads canonical (Mock-style) keys from rawPayload — add
        // them alongside Booking's native ones.
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
