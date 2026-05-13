@props([
    'label',
    'value',
    'tone' => 'slate',
    'icon' => null,
])

@php
    $tones = [
        'slate'   => 'bg-slate-50 text-slate-700 ring-slate-200',
        'indigo'  => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-200',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-200',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'violet'  => 'bg-violet-50 text-violet-700 ring-violet-200',
        'red'     => 'bg-red-50 text-red-700 ring-red-200',
    ];
    $cls = $tones[$tone] ?? $tones['slate'];
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-center justify-between">
        <span class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ $label }}</span>
        @if ($icon)
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full ring-1 ring-inset {{ $cls }}">{{ $icon }}</span>
        @endif
    </div>
    <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $value }}</div>
</div>
