<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Billing\Actions\GenerateInvoice;
use App\Domain\Exceptions\InvalidReservationState;
use App\Domain\Reservations\Support\ReservationStatusWriter;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckOutReservation
{
    public function __construct(
        private readonly ReservationStatusWriter $statuses,
        private readonly GenerateInvoice $generateInvoice,
    ) {
    }

    /**
     * @param array<int, array{description: string, amount: float, type?: string, taxable?: bool, tax_rate?: float}> $extraCharges
     */
    public function execute(
        Reservation $reservation,
        ?User $actor = null,
        array $extraCharges = [],
        ?string $note = null,
    ): Invoice {
        return DB::transaction(function () use ($reservation, $actor, $extraCharges, $note): Invoice {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
                throw InvalidReservationState::cannotTransition(
                    $reservation,
                    Reservation::STATUS_CHECKED_OUT,
                    'only checked-in reservations can be checked out',
                );
            }

            $this->applyExtraCharges($reservation, $extraCharges, $actor);

            $from = $reservation->status;

            $reservation->fill([
                'status'         => Reservation::STATUS_CHECKED_OUT,
                'checked_out_at' => now(),
                'updated_by'     => $actor?->id,
            ])->save();

            if ($reservation->room_id) {
                $room = Room::query()->lockForUpdate()->findOrFail($reservation->room_id);
                $room->fill(['status' => Room::STATUS_DIRTY])->save();
            }

            $this->statuses->record(
                $reservation,
                $from,
                Reservation::STATUS_CHECKED_OUT,
                $actor?->id,
                $note,
            );

            return $this->generateInvoice->execute($reservation);
        });
    }

    private function applyExtraCharges(Reservation $reservation, array $extraCharges, ?User $actor): void
    {
        foreach ($extraCharges as $charge) {
            $amount = (float) ($charge['amount'] ?? 0);
            $qty    = (float) ($charge['quantity'] ?? 1);
            $unit   = $qty > 0 ? round($amount / $qty, 2) : $amount;

            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'type'           => $charge['type'] ?? ReservationCharge::TYPE_FEE,
                'description'    => $charge['description'] ?? 'Extra charge',
                'quantity'       => $qty,
                'unit_price'     => $unit,
                'total'          => round($amount, 2),
                'taxable'        => (bool) ($charge['taxable'] ?? true),
                'tax_rate'       => (float) ($charge['tax_rate'] ?? 0),
                'added_by'       => $actor?->id,
                'added_at'       => now(),
            ]);
        }
    }
}
