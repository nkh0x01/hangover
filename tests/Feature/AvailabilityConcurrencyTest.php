<?php

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Pricing\PricingService;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Domain\Reservations\Support\ReservationCodeGenerator;
use App\Domain\Reservations\Support\ReservationStatusWriter;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\AvailabilityCalendar;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Overbooking prevention is defended in six layers:
 *
 *   1. Pre-quote availability check
 *   2. Domain invariant
 *   3. DB::transaction wrapping the write
 *   4. SELECT … FOR UPDATE row locks
 *   5. UNIQUE(room_id, date) on the ledger
 *   6. Nightly reconciliation job (Phase 2+)
 *
 * Layer 5 fires on every supported engine and is exercised on every CI
 * run (SQLite). Layers 3 and 4 are exercised by the deterministic
 * "serialised attempts" test below.
 *
 * Genuine multi-process race conditions (layers 3+4+5 together) require
 * MariaDB/MySQL. Set PMS_MYSQL_CONCURRENCY_DSN in the form
 *     "mysql://user:pass@host:port/database"
 * to enable the pcntl-forked parallel test.
 */

it('UNIQUE(room_id, date) prevents a single-process double-book', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id]);
    $room = Room::factory()->create([
        'property_id' => $property->id,
        'room_type_id' => $type->id,
    ]);

    AvailabilityCalendar::create([
        'property_id' => $property->id,
        'room_id' => $room->id,
        'date' => '2026-07-01',
        'status' => AvailabilityCalendar::STATUS_OPEN,
    ]);

    DB::transaction(function () use ($room) {
        AvailabilityCalendar::query()
            ->where('room_id', $room->id)
            ->where('date', '2026-07-01')
            ->update(['status' => AvailabilityCalendar::STATUS_BOOKED]);
    });

    expect(fn () => AvailabilityCalendar::create([
        'property_id' => $room->property_id,
        'room_id' => $room->id,
        'date' => '2026-07-01',
        'status' => AvailabilityCalendar::STATUS_BOOKED,
    ]))->toThrow(QueryException::class);
});

it('two serialised CreateReservation attempts: exactly one wins, the other gets RoomNotAvailable', function () {
    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id, 'base_price' => 100]);
    $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
    $guest = Guest::factory()->create(['property_id' => $property->id]);

    $period = new Period('2026-08-01', '2026-08-03');
    $action = new CreateReservation(
        new AvailabilityService(),
        new PricingService(),
        new ReservationCodeGenerator(),
        new ReservationStatusWriter(),
        new ReservationTotals(),
    );

    $payload = fn () => new CreateReservationData(
        property: $property->fresh(),
        guest: $guest->fresh(),
        roomType: $type->fresh(),
        period: $period,
        room: $room->fresh(),
        adults: 1,
    );

    $first  = null;
    $second = null;
    try {
        $first = $action->execute($payload());
    } catch (\Throwable $e) {
        // shouldn't happen
        throw $e;
    }
    try {
        $second = $action->execute($payload());
    } catch (\App\Domain\Exceptions\RoomNotAvailable $e) {
        // expected
    }

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(Reservation::where('room_id', $room->id)->whereDate('check_in_date', '2026-08-01')->count())->toBe(1);
});

it('parallel CreateReservation processes: exactly one wins (requires MySQL + pcntl)', function () {
    $dsn = getenv('PMS_MYSQL_CONCURRENCY_DSN');
    if (! $dsn) {
        $this->markTestSkipped('Set PMS_MYSQL_CONCURRENCY_DSN to enable the forked parallel test.');
    }
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('pcntl extension not loaded.');
    }

    // Parse the DSN: mysql://user:pass@host:port/database
    $parts = parse_url($dsn);
    if (! $parts || ($parts['scheme'] ?? '') !== 'mysql') {
        $this->markTestSkipped('Invalid PMS_MYSQL_CONCURRENCY_DSN (expected mysql://...).');
    }

    config()->set('database.connections.pms_mysql_concurrency', [
        'driver'   => 'mysql',
        'host'     => $parts['host'] ?? '127.0.0.1',
        'port'     => $parts['port'] ?? 3306,
        'database' => trim($parts['path'] ?? '', '/'),
        'username' => $parts['user'] ?? 'root',
        'password' => $parts['pass'] ?? '',
        'charset'  => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'   => '',
        'strict'   => true,
    ]);

    config()->set('database.default', 'pms_mysql_concurrency');
    DB::purge();
    \Artisan::call('migrate:fresh', ['--force' => true]);
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $property = Property::factory()->create();
    $type = RoomType::factory()->create(['property_id' => $property->id, 'base_price' => 100]);
    $room = Room::factory()->create(['property_id' => $property->id, 'room_type_id' => $type->id]);
    $guest = Guest::factory()->create(['property_id' => $property->id]);
    $period = new Period('2026-09-01', '2026-09-03');

    // Pre-populate availability ledger so processes don't race on row creation.
    app(AvailabilityService::class)->ensureRowsExist($room, $period);

    $concurrency = 5;
    $pids = [];
    for ($i = 0; $i < $concurrency; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('pcntl_fork failed');
        }
        if ($pid === 0) {
            // Child: fresh DB connection, attempt reservation.
            DB::reconnect();
            try {
                app(CreateReservation::class)->execute(new CreateReservationData(
                    property: $property->fresh(),
                    guest:    $guest->fresh(),
                    roomType: $type->fresh(),
                    period:   $period,
                    room:     $room->fresh(),
                    adults:   1,
                ));
                exit(0); // winner
            } catch (\App\Domain\Exceptions\RoomNotAvailable) {
                exit(2);
            } catch (\Throwable $e) {
                fwrite(STDERR, $e->getMessage().PHP_EOL);
                exit(1);
            }
        }
        $pids[] = $pid;
    }

    $winners = 0;
    $clean_losers = 0;
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        $exit = pcntl_wexitstatus($status);
        if ($exit === 0) {
            $winners++;
        } elseif ($exit === 2) {
            $clean_losers++;
        }
    }

    expect($winners)->toBe(1)
        ->and($clean_losers)->toBe($concurrency - 1)
        ->and(Reservation::where('room_id', $room->id)
            ->whereDate('check_in_date', '2026-09-01')->count())->toBe(1);
});
