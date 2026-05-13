<?php

use App\Domain\Availability\Period;
use App\Domain\Channels\Data\ChannelReservationDTO;
use App\Domain\Channels\Providers\MockChannelService;
use App\Domain\Channels\Services\ChannelConflictService;
use App\Domain\Channels\Services\ChannelMappingService;
use App\Domain\Channels\Services\ChannelReservationImportService;
use App\Domain\Channels\Services\ChannelSyncService;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelRoomMapping;
use App\Models\ChannelSyncLog;
use App\Models\DailyRoomPrice;
use App\Models\PricingRule;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();
    MockChannelService::reset();

    $this->connection = ChannelConnection::factory()->create([
        'property_id' => $this->p->property->id,
        'channel' => ChannelConnection::CHANNEL_MOCK,
    ]);

    $this->mapper = app(ChannelMappingService::class);
    $this->mapper->mapRoom($this->connection, $this->p->standardType, 'EXT-STD-1', 'Standard Double');

    $this->sync = app(ChannelSyncService::class);
    $this->importer = app(ChannelReservationImportService::class);
});

it('creates a channel connection with encrypted credentials', function () {
    $c = ChannelConnection::factory()->create([
        'property_id' => $this->p->property->id,
        'credentials' => ['hotel_id' => '12345', 'secret' => 'shhh'],
    ]);

    $raw = \DB::table('channel_connections')->where('id', $c->id)->value('credentials');
    expect($raw)->not->toContain('shhh');

    expect($c->fresh()->credentials)->toBe(['hotel_id' => '12345', 'secret' => 'shhh']);
});

it('maps an external room id to a room type', function () {
    $resolved = $this->mapper->roomTypeForExternal($this->connection, 'EXT-STD-1');
    expect($resolved->id)->toBe($this->p->standardType->id);
});

it('maps a rate plan and applies markup multiplicatively', function () {
    $rate = $this->mapper->mapRate(
        $this->connection,
        $this->p->standardType,
        'RATE-BAR',
        'BAR',
        markupPercent: 10,
        markupAbs: 5,
    );

    // 100 * 1.10 = 110, then + 5 = 115
    expect($this->mapper->applyMarkup(100.0, $rate))->toBe(115.0);
});

it('logs an entry every time a mock sync runs', function () {
    expect(ChannelSyncLog::where('channel_connection_id', $this->connection->id)->count())->toBe(0);

    $result = $this->sync->pushAvailability(
        $this->connection,
        new Period('2026-06-01', '2026-06-04'),
    );

    expect($result->ok)->toBeTrue();
    $log = ChannelSyncLog::where('channel_connection_id', $this->connection->id)->first();
    expect($log->action)->toBe(ChannelSyncLog::ACTION_PUSH_AVAILABILITY);
    expect($log->status)->toBe(ChannelSyncLog::STATUS_SUCCESS);
    expect($log->direction)->toBe(ChannelSyncLog::DIRECTION_OUT);
});

it('imports an inbound mock reservation as a real reservation', function () {
    MockChannelService::seedDefaultInbox($this->connection, '2026-07-01', '2026-07-04', 1);

    $result = $this->sync->pullReservations(
        $this->connection,
        new Period('2026-06-01', '2026-08-01'),
    );

    expect($result->ok)->toBeTrue();
    expect($result->processed)->toBe(1);

    $row = ChannelReservation::where('channel_connection_id', $this->connection->id)->first();
    expect($row->status)->toBe(ChannelReservation::STATUS_PROCESSED);
    expect($row->reservation_id)->not->toBeNull();
});

it('is idempotent — re-pulling the same external_id yields one reservation', function () {
    MockChannelService::seedDefaultInbox($this->connection, '2026-07-10', '2026-07-12', 1);

    $window = new Period('2026-06-01', '2026-08-01');
    $this->sync->pullReservations($this->connection, $window);
    $this->sync->pullReservations($this->connection, $window);

    expect(ChannelReservation::where('channel_connection_id', $this->connection->id)->count())->toBe(1);
    expect(\App\Models\Reservation::where('source', ChannelConnection::CHANNEL_MOCK)->count())->toBe(1);
});

it('flags a conflict when the channel reservation clashes with a local booking', function () {
    // Local reservation occupies all rooms of the standard type on these nights.
    foreach ($this->p->rooms as $room) {
        app(CreateReservation::class)->execute(new CreateReservationData(
            property: $this->p->property,
            guest:    $this->p->guest,
            roomType: $this->p->standardType,
            period:   new Period('2026-09-01', '2026-09-04'),
            room:     $room,
            adults:   1,
        ));
    }

    MockChannelService::seedDefaultInbox($this->connection, '2026-09-01', '2026-09-04', 1);

    $result = $this->sync->pullReservations(
        $this->connection,
        new Period('2026-08-01', '2026-10-01'),
    );

    $row = ChannelReservation::where('channel_connection_id', $this->connection->id)->first();
    expect($row->status)->toBe(ChannelReservation::STATUS_CONFLICT);
    expect($row->reservation_id)->toBeNull();
    // failure tally is non-zero
    expect($result->failed)->toBe(1);
});

it('push_availability snapshots availability from the local ledger, not duplicated state', function () {
    // Book one of the two standard rooms for a single night.
    app(CreateReservation::class)->execute(new CreateReservationData(
        property: $this->p->property,
        guest:    $this->p->guest,
        roomType: $this->p->standardType,
        period:   new Period('2026-06-01', '2026-06-02'),
        room:     $this->p->room(0),
        adults:   1,
    ));

    $this->sync->pushAvailability($this->connection, new Period('2026-06-01', '2026-06-03'));

    $payload = MockChannelService::$lastPush[$this->connection->id]['availability'];
    $byDate = collect($payload)->keyBy('date');

    expect($byDate['2026-06-01']['available'])->toBe(1); // 1 of 2 left
    expect($byDate['2026-06-02']['available'])->toBe(2); // both free
});

it('push_rates uses the pricing engine, no shadow rate table', function () {
    PricingRule::create([
        'property_id' => $this->p->property->id, 'name' => 'Summer',
        'type' => PricingRule::TYPE_SEASONAL, 'priority' => 100,
        'scope' => PricingRule::SCOPE_PROPERTY,
        'conditions' => [],
        'action' => ['type' => 'percent', 'value' => 50],
        'valid_from' => '2026-06-01', 'valid_to' => '2026-06-30',
        'active' => true,
    ]);
    // sanity: pricing engine sees the rule.
    $expectedFirstNight = app(PricingService::class)
        ->priceForStay($this->p->standardType, new Period('2026-06-10', '2026-06-11'))
        ->nights[0]->amount;
    expect($expectedFirstNight)->toBe(150.0);

    $this->sync->pushRates($this->connection, new Period('2026-06-10', '2026-06-13'));

    $rates = MockChannelService::$lastPush[$this->connection->id]['rates'];
    $first = collect($rates)->firstWhere('date', '2026-06-10');
    expect((float) $first['amount'])->toBe(150.0);
});

it('marks the connection errored after a failed sync', function () {
    MockChannelService::$shouldFail[$this->connection->id] = true;

    $result = $this->sync->testConnection($this->connection);

    expect($result->ok)->toBeFalse();
    $fresh = $this->connection->fresh();
    expect($fresh->error_count)->toBe(1);
    expect($fresh->last_error)->toContain('forced failure');

    $log = ChannelSyncLog::where('channel_connection_id', $this->connection->id)->latest('id')->first();
    expect($log->status)->toBe(ChannelSyncLog::STATUS_FAILED);
});

it('a conflict can be retried after the human moves the local reservation out of the way', function () {
    foreach ($this->p->rooms as $room) {
        app(CreateReservation::class)->execute(new CreateReservationData(
            property: $this->p->property,
            guest:    $this->p->guest,
            roomType: $this->p->standardType,
            period:   new Period('2026-10-01', '2026-10-03'),
            room:     $room,
            adults:   1,
        ));
    }

    MockChannelService::seedDefaultInbox($this->connection, '2026-10-01', '2026-10-03', 1);
    $this->sync->pullReservations($this->connection, new Period('2026-09-01', '2026-11-01'));
    $row = ChannelReservation::where('channel_connection_id', $this->connection->id)->firstOrFail();
    expect($row->status)->toBe(ChannelReservation::STATUS_CONFLICT);

    // Free the rooms — pretend the operator cancelled the locals.
    \App\Models\AvailabilityCalendar::where('property_id', $this->p->property->id)->update([
        'status' => \App\Models\AvailabilityCalendar::STATUS_OPEN,
        'reservation_id' => null,
    ]);

    $row = app(ChannelConflictService::class)->retry($row->fresh());
    expect($row->status)->toBe(ChannelReservation::STATUS_PROCESSED);
    expect($row->reservation_id)->not->toBeNull();
});
