<?php

namespace App\Livewire\Reservations;

use App\Domain\Exceptions\DomainException;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Domain\Reservations\Actions\RecordPayment;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reservation')]
#[Layout('layouts.app')]
class Show extends Component
{
    public Reservation $reservation;

    // Modal flags
    public bool $showPaymentModal = false;
    public bool $showChargeModal  = false;
    public bool $showCancelModal  = false;

    // Payment form
    public string $payMethod = Payment::METHOD_CASH;
    public ?float $payAmount = null;
    public string $payReference = '';

    // Charge form
    public string $chargeType = ReservationCharge::TYPE_FEE;
    public string $chargeDescription = '';
    public float $chargeAmount = 0;

    // Cancel form
    public string $cancelReason = '';

    public ?string $error = null;

    private function toast(string $message, string $tone = 'ok'): void
    {
        $this->dispatch('toast', tone: $tone, message: $message);
    }

    public function mount(Reservation $reservation): void
    {
        $this->reservation = $reservation;
    }

    private function refreshReservation(): void
    {
        $this->reservation->refresh()->load([
            'leadGuest', 'room.roomType', 'nightsBreakdown',
            'charges.addedBy', 'payments.receivedBy',
            'statusHistory.changedBy', 'invoice',
        ]);
    }

    public function checkIn(): void
    {
        try {
            app(CheckInReservation::class)->execute($this->reservation, auth()->user());
            $this->toast('Checked in.');
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
        $this->refreshReservation();
    }

    public function checkOut(): void
    {
        try {
            $invoice = app(CheckOutReservation::class)->execute($this->reservation, auth()->user());
            $this->toast("Checked out · Invoice {$invoice->number}");
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
        $this->refreshReservation();
    }

    public function openCancelModal(): void
    {
        $this->reset(['cancelReason', 'error']);
        $this->showCancelModal = true;
    }

    public function confirmCancel(): void
    {
        $this->validate(['cancelReason' => 'required|string|min:3']);
        try {
            app(CancelReservation::class)->execute(
                $this->reservation,
                $this->cancelReason,
                auth()->user(),
            );
            $this->toast('Reservation cancelled.', 'warn');
            $this->showCancelModal = false;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
        $this->refreshReservation();
    }

    public function openPaymentModal(): void
    {
        $this->reset(['payMethod', 'payAmount', 'payReference', 'error']);
        $this->payMethod = Payment::METHOD_CASH;
        $this->payAmount = round(
            (float) $this->reservation->grand_total - (float) $this->reservation->paid_total,
            2,
        );
        $this->showPaymentModal = true;
    }

    public function recordPayment(): void
    {
        $this->validate([
            'payMethod' => 'required|in:'.implode(',', Payment::METHODS),
            'payAmount' => 'required|numeric|not_in:0',
        ]);
        try {
            app(RecordPayment::class)->execute(
                $this->reservation,
                $this->payMethod,
                (float) $this->payAmount,
                auth()->user(),
                $this->payReference ?: null,
            );
            $this->toast('Payment recorded.');
            $this->showPaymentModal = false;
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
        }
        $this->refreshReservation();
    }

    public function openChargeModal(): void
    {
        $this->reset(['chargeType', 'chargeDescription', 'chargeAmount', 'error']);
        $this->chargeType = ReservationCharge::TYPE_FEE;
        $this->showChargeModal = true;
    }

    public function addCharge(): void
    {
        $this->validate([
            'chargeType'        => 'required|in:'.implode(',', ReservationCharge::TYPES),
            'chargeDescription' => 'required|string|min:2|max:200',
            'chargeAmount'      => 'required|numeric|not_in:0',
        ]);

        ReservationCharge::create([
            'reservation_id' => $this->reservation->id,
            'type'           => $this->chargeType,
            'description'    => $this->chargeDescription,
            'quantity'       => 1,
            'unit_price'     => $this->chargeAmount,
            'total'          => $this->chargeAmount,
            'taxable'        => true,
            'tax_rate'       => 0,
            'added_by'       => auth()->id(),
            'added_at'       => now(),
        ]);

        app(ReservationTotals::class)->recompute($this->reservation);
        $this->showChargeModal = false;
        $this->toast('Charge added.');
        $this->refreshReservation();
    }

    public function render()
    {
        $this->reservation->loadMissing([
            'leadGuest', 'room.roomType', 'nightsBreakdown',
            'charges.addedBy', 'payments.receivedBy',
            'statusHistory.changedBy', 'invoice',
        ]);

        return view('livewire.reservations.show', [
            'r' => $this->reservation,
        ]);
    }
}
