<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Reservations')]
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'payment')]
    public string $paymentFilter = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $reservations = Reservation::query()
            ->with(['leadGuest', 'room', 'roomType'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->paymentFilter, fn ($q) => $q->where('payment_status', $this->paymentFilter))
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('code', 'like', $term)
                      ->orWhereHas('leadGuest', function ($g) use ($term) {
                          $g->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('email', 'like', $term);
                      })
                      ->orWhereHas('room', fn ($r) => $r->where('number', 'like', $term));
                });
            })
            ->orderByDesc('check_in_date')
            ->paginate(20);

        return view('livewire.reservations.index', [
            'reservations'    => $reservations,
            'statuses'        => Reservation::STATUSES,
            'paymentStatuses' => Reservation::PAYMENT_STATUSES,
        ]);
    }
}
