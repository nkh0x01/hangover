@props([
    'value',
    'kind' => 'reservation', // 'reservation' | 'payment' | 'room'
])

@php
    $resTones = [
        'pending'      => 'bg-amber-50 text-amber-700 ring-amber-200',
        'confirmed'    => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'checked_in'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'checked_out'  => 'bg-slate-100 text-slate-700 ring-slate-300',
        'cancelled'    => 'bg-red-50 text-red-700 ring-red-200',
        'no_show'      => 'bg-zinc-100 text-zinc-700 ring-zinc-300',
    ];
    $payTones = [
        'unpaid'        => 'bg-red-50 text-red-700 ring-red-200',
        'partial'       => 'bg-amber-50 text-amber-700 ring-amber-200',
        'paid'          => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'refunded'      => 'bg-violet-50 text-violet-700 ring-violet-200',
        'platform_paid' => 'bg-sky-50 text-sky-700 ring-sky-200',
    ];
    $roomTones = [
        'available'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'clean'       => 'bg-sky-50 text-sky-700 ring-sky-200',
        'dirty'       => 'bg-amber-50 text-amber-700 ring-amber-200',
        'occupied'    => 'bg-rose-50 text-rose-700 ring-rose-200',
        'maintenance' => 'bg-slate-100 text-slate-700 ring-slate-300',
        'blocked'     => 'bg-zinc-100 text-zinc-700 ring-zinc-300',
    ];
    $map = match ($kind) {
        'payment' => $payTones,
        'room'    => $roomTones,
        default   => $resTones,
    };
    $cls = $map[$value] ?? 'bg-slate-100 text-slate-700 ring-slate-300';
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $cls }}">
    {{ __(str_replace('_', ' ', $value)) }}
</span>
