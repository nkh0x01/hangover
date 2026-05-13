<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Invoice')]
#[Layout('layouts.app')]
class Show extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['lines', 'reservation.leadGuest', 'reservation.room', 'property']);
    }

    public function render()
    {
        return view('livewire.invoices.show');
    }
}
