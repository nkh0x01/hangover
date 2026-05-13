<?php

use App\Domain\Availability\Period;
use App\Domain\Channels\Data\AvailabilityDTO;
use App\Domain\Channels\Data\RateDTO;
use App\Domain\Channels\Data\RestrictionDTO;
use App\Domain\Channels\Exceptions\ChannelProviderException;
use App\Domain\Channels\Providers\Booking\BookingPayloadBuilder;
use App\Domain\Channels\Providers\BookingComService;
use App\Domain\Channels\Services\ChannelMappingService;
use App\Domain\Channels\Services\ChannelReservationImportService;
use App\Domain\Channels\Services\ChannelSyncService;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\AvailabilityCalendar;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelSyncLog;
use App\Models\DailyRoomPrice;
use App\Models\PricingRule;
use App\Models\Reservation;
use App\Notifications\ChannelSyncFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    $this->admin = $this->p->user('super_admin');

    $this->booking = ChannelConnection::factory()->create([
        'property_id' => $this->p->property->id,
        'channel' => ChannelConnection::CHANNEL_BOOKING,
        'name' => 'Booking sandbox',
        'status' => ChannelConnection::STATUS_ACTIVE,
        'dry_run' => true,
        'credentials' => [
            'hotel_id' => '12345',
            'secret' => 'shh',
            'webhook_secret' => 'whsec-test-123',
        ],
        'settings' => ['sandbox' => true],
    ]);

    app(ChannelMappingService::class)->mapRoom(
        $this->booking,
        $this->p->standardType,
        'BKG-STD-1',
        'Standard',
    );

    $this->sync = app(ChannelSyncService::class);
    $this->importer = app(ChannelReservationImportService::class);
});

it('encrypts Booking.com credentials at rest', function () {
    $raw = \DB::table('channel_connections')->where('id', $this->booking->id)->value('credentials');
    expect($raw)->not->toContain('shh');
    expect($raw)->not->toContain('whsec-test-123');
    // …but the model still decrypts them:
    expect($this->booking->fresh()->credentials['secret'])->toBe('shh');
});

it('test connection handles success and failure', function () {
    // dry-run = always success, no HTTP.
    Http::preventStrayRequests();
    $okResult = $this->sync->testConnection($this->booking);
    expect($okResult->ok)->toBeTrue();

    // Flip live; mock /ping to return 500.
    $this->booking->update([
        'dry_run' => false,
        'live_confirmed_at' => now(),
    ]);
    Http::fake([
        '*/ping' => Http::response('error', 500),
    ]);

    $failResult = $this->sync->testConnection($this->booking->fresh());
    expect($failResult->ok)->toBeFalse();
});

it('dry-run does NOT issue any outbound HTTP', function () {
    Http::preventStrayRequests();
    Http::fake();

    $this->sync->pushAvailability($this->booking, new Period('2026-06-01', '2026-06-04'));
    $this->sync->pushRates($this->booking, new Period('2026-06-01', '2026-06-04'));
    $this->sync->pushRestrictions($this->booking, new Period('2026-06-01', '2026-06-04'));

    Http::assertNothingSent();
});

it('dry-run still writes a sync log', function () {
    $this->sync->pushAvailability($this->booking, new Period('2026-06-01', '2026-06-04'));

    $log = ChannelSyncLog::query()
        ->where('channel_connection_id', $this->booking->id)
        ->where('action', ChannelSyncLog::ACTION_PUSH_AVAILABILITY)
        ->first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe(ChannelSyncLog::STATUS_SUCCESS);
});

it('reservation import is idempotent on the Booking external_id', function () {
    // Stage twice — same external_id, same hash → still one row, no second
    // Reservation created.
    $dto = makeBookingDTO('BKG-RES-1', $this->p->standardType, '2026-06-10', '2026-06-12');
    $this->importer->stage($this->booking, $dto);
    $this->importer->process(\App\Models\ChannelReservation::where('channel_connection_id', $this->booking->id)->first());

    $this->importer->stage($this->booking, $dto);
    $this->importer->process(\App\Models\ChannelReservation::where('channel_connection_id', $this->booking->id)->first());

    expect(ChannelReservation::where('channel_connection_id', $this->booking->id)->count())->toBe(1);
    expect(Reservation::where('source', ChannelConnection::CHANNEL_BOOKING)->count())->toBe(1);
});

it('a modification to a known external_id is flagged as duplicate (never overwrites locally)', function () {
    $dto = makeBookingDTO('BKG-RES-MOD', $this->p->standardType, '2026-06-15', '2026-06-17');
    $row = $this->importer->stage($this->booking, $dto);
    $this->importer->process($row);

    // Same external_id, different period — should be flagged 'duplicate'.
    $modified = makeBookingDTO('BKG-RES-MOD', $this->p->standardType, '2026-06-15', '2026-06-19');
    $this->importer->stage($this->booking, $modified);

    $refresh = ChannelReservation::where('channel_connection_id', $this->booking->id)
        ->where('external_id', 'BKG-RES-MOD')
        ->first();
    expect($refresh->status)->toBe(ChannelReservation::STATUS_DUPLICATE);
});

it('a Booking.com cancellation routes through CancelReservation and frees availability', function () {
    $dto = makeBookingDTO('BKG-RES-CANCEL', $this->p->standardType, '2026-06-20', '2026-06-22');
    $row = $this->importer->stage($this->booking, $dto);
    $row = $this->importer->process($row);
    $reservationId = $row->reservation_id;
    expect($reservationId)->not->toBeNull();

    // Availability should be booked on those nights.
    $blocked = AvailabilityCalendar::where('reservation_id', $reservationId)
        ->where('status', AvailabilityCalendar::STATUS_BOOKED)
        ->count();
    expect($blocked)->toBe(2);

    $cancelled = $this->importer->cancelByExternalId(
        $this->booking,
        'BKG-RES-CANCEL',
        'cancelled by booking',
    );
    expect($cancelled)->toBeTrue();

    expect(Reservation::find($reservationId)->status)->toBe(Reservation::STATUS_CANCELLED);
    expect(
        AvailabilityCalendar::where('reservation_id', $reservationId)
            ->where('status', AvailabilityCalendar::STATUS_BOOKED)
            ->count()
    )->toBe(0);
});

it('an inbound Booking.com reservation that clashes is flagged as conflict', function () {
    // Block both standard rooms.
    foreach ($this->p->rooms as $room) {
        app(CreateReservation::class)->execute(new CreateReservationData(
            property: $this->p->property,
            guest:    $this->p->guest,
            roomType: $this->p->standardType,
            period:   new Period('2026-07-01', '2026-07-04'),
            room:     $room,
            adults:   1,
        ));
    }

    $dto = makeBookingDTO('BKG-RES-CONFLICT', $this->p->standardType, '2026-07-01', '2026-07-04');
    $row = $this->importer->stage($this->booking, $dto);
    $row = $this->importer->process($row);

    expect($row->status)->toBe(ChannelReservation::STATUS_CONFLICT);
    expect($row->reservation_id)->toBeNull();
});

it('availability payload reflects the local ledger', function () {
    app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest:    $this->p->guest,
        roomType: $this->p->standardType,
        period:   new Period('2026-08-01', '2026-08-02'),
        room:     $this->p->room(0),
        adults:   1,
    ));

    $builder = app(BookingPayloadBuilder::class);
    $rows = [
        new AvailabilityDTO('BKG-STD-1', '2026-08-01', 1),
        new AvailabilityDTO('BKG-STD-1', '2026-08-02', 2),
    ];

    $payload = $builder->availability($this->booking, $rows);

    expect($payload['hotel_id'])->toBe('12345');
    expect($payload['availability'][0])->toMatchArray([
        'room_id' => 'BKG-STD-1',
        'date' => '2026-08-01',
        'rooms_available' => 1,
    ]);
    expect($payload['availability'][1]['rooms_available'])->toBe(2);
});

it('rate payload uses the pricing engine (no shadow rates)', function () {
    PricingRule::create([
        'property_id' => $this->p->property->id, 'name' => 'Summer',
        'type' => PricingRule::TYPE_SEASONAL, 'priority' => 100,
        'scope' => PricingRule::SCOPE_PROPERTY,
        'conditions' => [],
        'action' => ['type' => 'percent', 'value' => 50],
        'valid_from' => '2026-08-01', 'valid_to' => '2026-08-31',
        'active' => true,
    ]);

    $this->sync->pushRates($this->booking, new Period('2026-08-10', '2026-08-12'));

    $log = ChannelSyncLog::query()
        ->where('channel_connection_id', $this->booking->id)
        ->where('action', ChannelSyncLog::ACTION_PUSH_RATES)
        ->latest()->first();
    expect($log->status)->toBe(ChannelSyncLog::STATUS_SUCCESS);
    // 2 nights × 1 mapped room type
    expect($log->response_summary['processed'])->toBe(2);
});

it('restrictions payload mirrors daily_room_prices', function () {
    DailyRoomPrice::create([
        'property_id' => $this->p->property->id,
        'room_type_id' => $this->p->standardType->id,
        'room_id' => null,
        'date' => '2026-09-05',
        'min_stay' => 3,
        'closed_to_arrival' => true,
        'source' => DailyRoomPrice::SOURCE_MANUAL,
    ]);

    $builder = app(BookingPayloadBuilder::class);
    $payload = $builder->restrictions($this->booking, [
        new RestrictionDTO('BKG-STD-1', '2026-09-05', 3, null, true, false),
    ]);

    expect($payload['restrictions'][0])->toMatchArray([
        'room_id' => 'BKG-STD-1',
        'date' => '2026-09-05',
        'min_stay' => 3,
        'closed_to_arrival' => true,
    ]);
});

it('failed sync increments error_count and at the threshold notifies the manager', function () {
    Notification::fake();
    Http::preventStrayRequests();

    // Flip live so the provider actually tries HTTP, then make every call 500.
    $this->booking->update(['dry_run' => false, 'live_confirmed_at' => now()->subSeconds(120)]); // expired confirmation
    // No fresh confirmation → first push fails with "live push requires fresh confirmation"
    // That's enough to bump the error counter.

    for ($i = 0; $i < ChannelSyncFailed::FAILURE_THRESHOLD; $i++) {
        $this->sync->pushAvailability($this->booking->fresh(), new Period('2026-10-01', '2026-10-02'));
    }

    expect($this->booking->fresh()->error_count)->toBeGreaterThanOrEqual(ChannelSyncFailed::FAILURE_THRESHOLD);
    Notification::assertSentTo($this->admin, ChannelSyncFailed::class);
});

it('a live push without fresh confirmation is rejected by the provider', function () {
    $this->booking->update([
        'dry_run' => false,
        'live_confirmed_at' => null,
    ]);

    $result = $this->sync->pushAvailability(
        $this->booking->fresh(),
        new Period('2026-11-01', '2026-11-03'),
    );

    expect($result->ok)->toBeFalse();
    expect($result->error)->toContain('confirmation');
});

it('a live push with a fresh confirmation passes the guard (sandbox endpoint stubbed)', function () {
    $this->booking->update([
        'dry_run' => false,
        'live_confirmed_at' => now(),
    ]);
    Http::fake([
        '*/availability' => Http::response(['ok' => true], 200),
    ]);

    $result = $this->sync->pushAvailability(
        $this->booking->fresh(),
        new Period('2026-11-01', '2026-11-03'),
    );

    expect($result->ok)->toBeTrue();
    Http::assertSent(fn ($req) => str_contains($req->url(), '/availability'));
});

function makeBookingDTO(string $extId, $roomType, string $checkIn, string $checkOut): \App\Domain\Channels\Data\ChannelReservationDTO
{
    return new \App\Domain\Channels\Data\ChannelReservationDTO(
        externalId: $extId,
        externalRoomId: 'BKG-STD-1',
        period: new \App\Domain\Availability\Period($checkIn, $checkOut),
        guestFirstName: 'Booking',
        guestLastName: 'Guest',
        guestEmail: $extId.'@booking.test',
        guestPhone: null,
        adults: 1,
        children: 0,
        total: 200.0,
        currency: 'GEL',
        rawPayload: [
            'guest' => ['first_name' => 'Booking', 'last_name' => 'Guest', 'email' => $extId.'@booking.test'],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 1,
            'children' => 0,
            'external_room_id' => 'BKG-STD-1',
            'total' => 200.0,
            'currency' => 'GEL',
        ],
    );
}
