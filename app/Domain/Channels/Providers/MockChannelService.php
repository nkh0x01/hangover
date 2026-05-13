<?php

namespace App\Domain\Channels\Providers;

use App\Domain\Availability\Period;
use App\Domain\Channels\Contracts\ChannelProviderInterface;
use App\Domain\Channels\Data\ChannelReservationDTO;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Models\ChannelConnection;
use App\Models\ChannelRoomMapping;

/**
 * In-memory channel for development, tests, screenshots, and demos.
 *
 * It never reaches the network. The seeded "inbox" of inbound reservations
 * is kept as static state so tests can prime it deterministically. Outbound
 * push_* calls record their last payload so we can assert on them.
 *
 * The real Booking/Expedia/Airbnb providers will replace this in later phases.
 */
class MockChannelService implements ChannelProviderInterface
{
    /** @var array<int, array<int, ChannelReservationDTO>> by connection_id */
    public static array $inbox = [];

    /** @var array<int, array<string, mixed>> last pushed payloads by connection_id */
    public static array $lastPush = [];

    /** @var array<int, bool> health override by connection_id (default true) */
    public static array $health = [];

    /** @var array<int, bool> next call should throw (by connection_id) */
    public static array $shouldFail = [];

    /** @var array<int, array<int, string>> queued cancellations by connection_id */
    public static array $cancellations = [];

    public function key(): string
    {
        return ChannelConnection::CHANNEL_MOCK;
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
        $this->maybeFail($connection);
        return self::$health[$connection->id] ?? true;
    }

    public function pullReservations(ChannelConnection $connection, Period $window): iterable
    {
        $this->maybeFail($connection);

        foreach (self::$inbox[$connection->id] ?? [] as $dto) {
            if ($dto->period->checkIn->lessThan($window->checkOut)
                && $dto->period->checkOut->greaterThan($window->checkIn)
            ) {
                yield $dto;
            }
        }
    }

    public function pullCancellations(ChannelConnection $connection, Period $window): iterable
    {
        $this->maybeFail($connection);
        foreach (self::$cancellations[$connection->id] ?? [] as $externalId) {
            yield $externalId;
        }
    }

    public static function seedCancellation(ChannelConnection $connection, string $externalId): void
    {
        self::$cancellations[$connection->id][] = $externalId;
    }

    public function pushAvailability(ChannelConnection $connection, array $rows): void
    {
        $this->maybeFail($connection);
        self::$lastPush[$connection->id]['availability'] = array_map(
            fn ($r) => $r->toArray(),
            $rows,
        );
    }

    public function pushRates(ChannelConnection $connection, array $rows): void
    {
        $this->maybeFail($connection);
        self::$lastPush[$connection->id]['rates'] = array_map(
            fn ($r) => $r->toArray(),
            $rows,
        );
    }

    public function pushRestrictions(ChannelConnection $connection, array $rows): void
    {
        $this->maybeFail($connection);
        self::$lastPush[$connection->id]['restrictions'] = array_map(
            fn ($r) => $r->toArray(),
            $rows,
        );
    }

    /**
     * Test helper — seed an inbound reservation onto the mock provider.
     */
    public static function seedInbound(ChannelConnection $connection, ChannelReservationDTO $dto): void
    {
        self::$inbox[$connection->id][] = $dto;
    }

    public static function reset(): void
    {
        self::$inbox = [];
        self::$lastPush = [];
        self::$health = [];
        self::$shouldFail = [];
        self::$cancellations = [];
    }

    /**
     * Quick way to wire the mock to a connection's mapped rooms. Generates
     * deterministic DTOs the test/seed can rely on.
     */
    public static function seedDefaultInbox(ChannelConnection $connection, string $checkIn, string $checkOut, int $count = 2): void
    {
        $mappings = ChannelRoomMapping::where('channel_connection_id', $connection->id)->get();
        if ($mappings->isEmpty()) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $mapping = $mappings[$i % $mappings->count()];
            self::seedInbound($connection, new ChannelReservationDTO(
                externalId: 'MOCK-'.($connection->id).'-'.($i + 1),
                externalRoomId: (string) $mapping->external_room_id,
                period: new Period($checkIn, $checkOut),
                guestFirstName: ['Alex', 'Maria', 'David'][$i % 3],
                guestLastName: ['Smith', 'Garcia', 'Beridze'][$i % 3],
                guestEmail: 'guest'.($i + 1).'@mock.test',
                guestPhone: '+995555000'.($i + 1),
                adults: 2,
                children: 0,
                total: 250.0 + ($i * 20),
                currency: 'GEL',
                rawPayload: [
                    'guest' => [
                        'first_name' => ['Alex', 'Maria', 'David'][$i % 3],
                        'last_name'  => ['Smith', 'Garcia', 'Beridze'][$i % 3],
                        'email'      => 'guest'.($i + 1).'@mock.test',
                    ],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'adults' => 2,
                    'children' => 0,
                    'external_room_id' => (string) $mapping->external_room_id,
                    'total' => 250.0 + ($i * 20),
                    'currency' => 'GEL',
                ],
            ));
        }
    }

    private function maybeFail(ChannelConnection $connection): void
    {
        if (! empty(self::$shouldFail[$connection->id])) {
            self::$shouldFail[$connection->id] = false;
            throw new ChannelProviderException('Mock provider forced failure.');
        }
    }
}
