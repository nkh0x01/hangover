<?php

namespace App\Console\Commands;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Availability\Period;
use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Exceptions\RoomNotAvailable;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\MoveReservation;
use App\Domain\Reservations\Actions\RecordPayment;
use App\Domain\Reservations\Data\CreateReservationData;
use App\Models\AvailabilityCalendar;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Console\Command;
use OwenIt\Auditing\Models\Audit;

/**
 * One-shot end-to-end Phase 1 verification. Runs against a wiped, freshly
 * seeded database so it never depends on prior state. Prints a PASS/FAIL
 * banner per scenario and exits non-zero if anything fails.
 */
class Phase1Verify extends Command
{
    protected $signature = 'pms:phase1-verify';

    protected $description = 'Run end-to-end verification of Phase 1 domain logic.';

    /** @var array<int, array{name: string, ok: bool, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

        $property = Property::first();
        $room1 = $property->rooms()->where('number', '101')->first();
        $room2 = $property->rooms()->where('number', '102')->first();
        $standardType = $property->roomTypes()->where('slug', 'standard')->first();
        $guestA = Guest::factory()->create(['property_id' => $property->id, 'first_name' => 'Alice']);
        $guestB = Guest::factory()->create(['property_id' => $property->id, 'first_name' => 'Bob']);
        $guestC = Guest::factory()->create(['property_id' => $property->id, 'first_name' => 'Carol']);

        // ----- 1. Reservation creation -----
        $r1 = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property,
            guest: $guestA,
            roomType: $standardType,
            period: new Period('2026-05-10', '2026-05-12'),
            room: $room1,
            adults: 2,
        ));
        $this->check(
            '1. Reservation created',
            $r1->status === Reservation::STATUS_CONFIRMED && $r1->nights === 2,
            sprintf('code=%s nights=%d grand=%s', $r1->code, $r1->nights, $r1->grand_total),
        );

        // ----- 2. Same room/date overbooking is blocked -----
        try {
            app(CreateReservation::class)->execute(new CreateReservationData(
                property: $property,
                guest: $guestB,
                roomType: $standardType,
                period: new Period('2026-05-11', '2026-05-13'),
                room: $room1,
                adults: 1,
            ));
            $this->check('2. Overbooking blocked', false, 'expected RoomNotAvailable, got success');
        } catch (RoomNotAvailable) {
            $this->check('2. Overbooking blocked', true, 'RoomNotAvailable thrown as expected');
        }

        // ----- 3. Back-to-back stays (half-open) -----
        $rB = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property,
            guest: $guestB,
            roomType: $standardType,
            period: new Period('2026-05-12', '2026-05-14'),
            room: $room1,
            adults: 1,
        ));
        $this->check(
            '3. Back-to-back stay (May 10-12 + May 12-14)',
            $rB->status === Reservation::STATUS_CONFIRMED,
            sprintf('R1 occupies %s/%s, R2 occupies %s/%s',
                $r1->check_in_date->toDateString(), '2026-05-11',
                $rB->check_in_date->toDateString(), '2026-05-13'),
        );

        // ----- 4. Cancel releases availability -----
        $rCancel = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property, guest: $guestC, roomType: $standardType,
            period: new Period('2026-06-01', '2026-06-03'),
            room: $room1,
        ));
        app(CancelReservation::class)->execute($rCancel, 'test cancel');
        $row = AvailabilityCalendar::where('room_id', $room1->id)
            ->where('date', '2026-06-01')->first();
        $this->check(
            '4. Cancel releases availability',
            $row->status === AvailabilityCalendar::STATUS_OPEN && $row->reservation_id === null,
            sprintf('row.status=%s reservation_id=%s', $row->status, $row->reservation_id ?? 'null'),
        );

        // ----- 5. Check-in -> reservation checked_in, room occupied -----
        $rCheckin = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property, guest: $guestC, roomType: $standardType,
            period: new Period('2026-07-01', '2026-07-03'),
            room: $room2,
        ));
        app(CheckInReservation::class)->execute($rCheckin);
        $rCheckin->refresh();
        $room2->refresh();
        $this->check(
            '5. Check-in transitions states',
            $rCheckin->status === Reservation::STATUS_CHECKED_IN
                && $room2->status === Room::STATUS_OCCUPIED
                && $rCheckin->checked_in_at !== null,
            sprintf('reservation=%s room=%s', $rCheckin->status, $room2->status),
        );

        // ----- 6. Check-out -> reservation checked_out, room dirty, invoice generated -----
        $invoice = app(CheckOutReservation::class)->execute($rCheckin);
        $rCheckin->refresh();
        $room2->refresh();
        $this->check(
            '6. Check-out transitions states + invoice',
            $rCheckin->status === Reservation::STATUS_CHECKED_OUT
                && $room2->status === Room::STATUS_DIRTY
                && $invoice !== null,
            sprintf('reservation=%s room=%s invoice=%s lines=%d',
                $rCheckin->status, $room2->status, $invoice->number, $invoice->lines->count()),
        );

        // ----- 7. Payment status: unpaid -> partial -> paid -----
        $rPay = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property, guest: $guestA, roomType: $standardType,
            period: new Period('2026-08-01', '2026-08-04'),
            room: $room1,
        ));
        $grand = (float) $rPay->grand_total;

        $unpaidOk = $rPay->payment_status === Reservation::PAYMENT_UNPAID;

        app(RecordPayment::class)->execute($rPay, Payment::METHOD_CASH, $grand / 2);
        $rPay->refresh();
        $partialOk = $rPay->payment_status === Reservation::PAYMENT_PARTIAL;

        app(RecordPayment::class)->execute($rPay, Payment::METHOD_CARD, $grand / 2);
        $rPay->refresh();
        $paidOk = $rPay->payment_status === Reservation::PAYMENT_PAID;

        $this->check(
            '7. Payment status: unpaid → partial → paid',
            $unpaidOk && $partialOk && $paidOk,
            sprintf('unpaid=%s partial=%s paid=%s', $unpaidOk ? 'Y' : 'N', $partialOk ? 'Y' : 'N', $paidOk ? 'Y' : 'N'),
        );

        // ----- 8. Invoice generation -----
        $this->check(
            '8. Invoice has snapshot lines',
            $invoice->lines->count() >= 2 && (float) $invoice->total > 0,
            sprintf('lines=%d total=%s', $invoice->lines->count(), $invoice->total),
        );

        // ----- 9. Audit logs -----
        $auditCounts = [
            'reservation' => Audit::where('auditable_type', Reservation::class)->count(),
            'room'        => Audit::where('auditable_type', Room::class)->count(),
            'payment'     => Audit::where('auditable_type', Payment::class)->count(),
        ];
        $this->check(
            '9. Audit logs written',
            $auditCounts['reservation'] > 0 && $auditCounts['room'] > 0 && $auditCounts['payment'] > 0,
            sprintf('reservation=%d room=%d payment=%d',
                $auditCounts['reservation'], $auditCounts['room'], $auditCounts['payment']),
        );

        // ----- 10. Reservation status history -----
        $rCheckin->load('statusHistory');
        $history = $rCheckin->statusHistory->pluck('to_status')->all();
        $this->check(
            '10. Reservation status history written',
            $history === [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_CHECKED_OUT,
            ],
            'history=['.implode(' → ', $history).']',
        );

        // ----- 11. Calendar matrix -----
        $matrix = app(AvailabilityService::class)->matrix(
            $property,
            new Period('2026-05-10', '2026-05-14'),
        );
        $r1Row1010 = $matrix[$room1->id]['2026-05-10']->status;
        $r1Row1013 = $matrix[$room1->id]['2026-05-13']->status;
        $r2Row1010 = $matrix[$room2->id]['2026-05-10']->status;
        $this->check(
            '11. Matrix reflects booked vs open',
            $r1Row1010 === 'booked'
                && $r1Row1013 === 'booked'   // booked by guestB
                && $r2Row1010 === 'open',
            sprintf('room1[10]=%s room1[13]=%s room2[10]=%s', $r1Row1010, $r1Row1013, $r2Row1010),
        );

        // ----- 12. MoveReservation works and rolls back on conflict -----
        $rMove = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property, guest: $guestC, roomType: $standardType,
            period: new Period('2026-09-01', '2026-09-03'),
            room: $room1,
        ));
        // Move to a free range — should succeed
        app(MoveReservation::class)->execute($rMove, new Period('2026-09-10', '2026-09-13'));
        $rMove->refresh();
        $movedOk = $rMove->check_in_date->toDateString() === '2026-09-10';

        // Pre-book some other dates
        $rBlock = app(CreateReservation::class)->execute(new CreateReservationData(
            property: $property, guest: $guestA, roomType: $standardType,
            period: new Period('2026-10-01', '2026-10-05'),
            room: $room1,
        ));
        // Try to move rMove onto a conflict — should throw and leave rMove untouched
        $rolledBack = false;
        try {
            app(MoveReservation::class)->execute($rMove, new Period('2026-10-02', '2026-10-04'), $room1);
        } catch (RoomNotAvailable) {
            $rolledBack = true;
        }
        $rMove->refresh();
        $stayedPut = $rMove->check_in_date->toDateString() === '2026-09-10';

        $this->check(
            '12. MoveReservation succeeds; rolls back on conflict',
            $movedOk && $rolledBack && $stayedPut,
            sprintf('moved=%s rolled_back=%s stayed_put=%s',
                $movedOk ? 'Y' : 'N', $rolledBack ? 'Y' : 'N', $stayedPut ? 'Y' : 'N'),
        );

        // ----- Report -----
        $this->newLine();
        $this->info('═══ Phase 1 Verification Results ═══');
        $allOk = true;
        foreach ($this->results as $r) {
            $status = $r['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $this->line(sprintf('  [%s] %-60s %s', $status, $r['name'], $r['detail']));
            $allOk = $allOk && $r['ok'];
        }
        $this->newLine();
        $this->line($allOk ? '<fg=green>All scenarios passed.</>' : '<fg=red>One or more scenarios failed.</>');

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $name, bool $ok, string $detail = ''): void
    {
        $this->results[] = compact('name', 'ok', 'detail');
    }
}
