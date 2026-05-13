<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $property = Property::query()->first();
        $today    = Carbon::today();

        $arrivalsToday = Reservation::query()
            ->where('property_id', $property?->id)
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])
            ->with(['leadGuest', 'room'])
            ->orderBy('check_in_date')
            ->get();

        $departuresToday = Reservation::query()
            ->where('property_id', $property?->id)
            ->whereDate('check_out_date', $today)
            ->whereIn('status', [Reservation::STATUS_CHECKED_IN, Reservation::STATUS_CHECKED_OUT])
            ->with(['leadGuest', 'room'])
            ->orderBy('check_out_date')
            ->get();

        $roomCounts = Room::query()
            ->where('property_id', $property?->id)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $occupied = $roomCounts[Room::STATUS_OCCUPIED] ?? 0;
        $dirty    = $roomCounts[Room::STATUS_DIRTY] ?? 0;
        $available = ($roomCounts[Room::STATUS_AVAILABLE] ?? 0) + ($roomCounts[Room::STATUS_CLEAN] ?? 0);

        $revenueToday = (float) Payment::query()
            ->where('property_id', $property?->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereDate('paid_at', $today)
            ->sum('amount');

        $unpaid = Reservation::query()
            ->where('property_id', $property?->id)
            ->whereIn('payment_status', [Reservation::PAYMENT_UNPAID, Reservation::PAYMENT_PARTIAL])
            ->whereIn('status', [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
                Reservation::STATUS_CHECKED_OUT,
            ])
            ->count();

        return view('livewire.dashboard', [
            'property'        => $property,
            'arrivalsToday'   => $arrivalsToday,
            'departuresToday' => $departuresToday,
            'occupied'        => $occupied,
            'available'       => $available,
            'dirty'           => $dirty,
            'revenueToday'    => $revenueToday,
            'unpaid'          => $unpaid,
            'today'           => $today,
        ]);
    }
}
