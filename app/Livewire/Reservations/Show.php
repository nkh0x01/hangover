<?php

namespace App\Livewire\Reservations;

use App\Domain\Exceptions\DomainException;
use App\Domain\Inventory\Actions\SellToReservation;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CheckOutReservation;
use App\Domain\Reservations\Actions\RecordPayment;
use App\Domain\Reservations\Support\ReservationTotals;
use App\Models\InventoryLocation;
use App\Models\Payment;
use App\Models\Product;
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
    public bool $showSellModal    = false;

    // Sell product form
    public ?int $sellProductId = null;
    public int $sellQuantity = 1;
    public ?int $sellLocationId = null;

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
            $this->toast(__('Checked in.'));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
        $this->refreshReservation();
    }

    public function checkOut(): void
    {
        try {
            $invoice = app(CheckOutReservation::class)->execute($this->reservation, auth()->user());
            $this->toast(__('Checked out · Invoice :number', ['number' => $invoice->number]));
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
            $this->toast(__('Reservation cancelled.'), 'warn');
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
            $this->toast(__('Payment recorded.'));
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
        $this->toast(__('Charge added.'));
        $this->refreshReservation();
    }

    public function openSellModal(): void
    {
        $this->reset(['sellProductId', 'sellQuantity', 'sellLocationId', 'error']);
        $this->sellQuantity = 1;
        // Default to the reservation's own minibar if there is one, else reception.
        $minibar = $this->reservation->room?->minibarLocation;
        if ($minibar) {
            $this->sellLocationId = $minibar->id;
        } else {
            $this->sellLocationId = InventoryLocation::query()
                ->where('property_id', $this->reservation->property_id)
                ->where('type', InventoryLocation::TYPE_RECEPTION)
                ->value('id');
        }
        $this->showSellModal = true;
    }

    public function sellProduct(): void
    {
        $this->validate([
            'sellProductId'  => 'required|integer|exists:products,id',
            'sellQuantity'   => 'required|integer|min:1|max:99',
            'sellLocationId' => 'required|integer|exists:inventory_locations,id',
        ]);

        try {
            $product = Product::findOrFail($this->sellProductId);
            $location = InventoryLocation::findOrFail($this->sellLocationId);

            app(SellToReservation::class)->execute(
                $this->reservation, $product, $location, $this->sellQuantity, auth()->user(),
            );
            $this->toast(__('Product added to folio.'));
            $this->showSellModal = false;
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'error');
        }

        $this->refreshReservation();
    }

    public function render()
    {
        $this->reservation->loadMissing([
            'leadGuest', 'room.roomType', 'nightsBreakdown',
            'charges.addedBy', 'payments.receivedBy',
            'statusHistory.changedBy', 'invoice',
        ]);

        $products = Product::query()
            ->where('property_id', $this->reservation->property_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $sellLocations = InventoryLocation::query()
            ->where('property_id', $this->reservation->property_id)
            ->where(function ($q) {
                $q->whereIn('type', [InventoryLocation::TYPE_RECEPTION, InventoryLocation::TYPE_STORAGE])
                  ->orWhere('room_id', $this->reservation->room_id);
            })
            ->orderBy('type')
            ->get();

        return view('livewire.reservations.show', [
            'r' => $this->reservation,
            'sellProducts' => $products,
            'sellLocations' => $sellLocations,
        ]);
    }
}
