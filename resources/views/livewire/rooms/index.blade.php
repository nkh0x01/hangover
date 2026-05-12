<div x-data="{ openMenu: null }">
    <x-slot name="header">Rooms</x-slot>

    @php
        $tones = [
            'available'   => ['bg' => 'bg-emerald-50', 'ring' => 'ring-emerald-200', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
            'clean'       => ['bg' => 'bg-sky-50',     'ring' => 'ring-sky-200',     'text' => 'text-sky-700',     'dot' => 'bg-sky-500'],
            'dirty'       => ['bg' => 'bg-amber-50',   'ring' => 'ring-amber-200',   'text' => 'text-amber-700',   'dot' => 'bg-amber-500'],
            'occupied'    => ['bg' => 'bg-rose-50',    'ring' => 'ring-rose-200',    'text' => 'text-rose-700',    'dot' => 'bg-rose-500'],
            'maintenance' => ['bg' => 'bg-slate-100',  'ring' => 'ring-slate-300',   'text' => 'text-slate-700',   'dot' => 'bg-slate-500'],
            'blocked'     => ['bg' => 'bg-zinc-100',   'ring' => 'ring-zinc-300',    'text' => 'text-zinc-700',    'dot' => 'bg-zinc-500'],
        ];
        $statuses = ['available', 'clean', 'dirty', 'maintenance', 'blocked'];
    @endphp

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
        @foreach ($rooms as $room)
            @php $t = $tones[$room->status] ?? $tones['available']; @endphp
            <div class="relative rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="text-2xl font-bold text-slate-900">{{ $room->number }}</div>
                        <div class="text-xs text-slate-500">Floor {{ $room->floor }} · {{ $room->roomType?->name }}</div>
                    </div>
                    <button type="button"
                            class="text-slate-400 hover:text-slate-600"
                            @click="openMenu === {{ $room->id }} ? openMenu = null : openMenu = {{ $room->id }}">
                        ⋯
                    </button>
                </div>
                <div class="mt-4 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset {{ $t['bg'] }} {{ $t['text'] }} {{ $t['ring'] }}">
                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $t['dot'] }}"></span>
                    {{ $room->status }}
                </div>

                <div x-show="openMenu === {{ $room->id }}" x-cloak
                     @click.outside="openMenu = null"
                     class="absolute right-3 top-12 z-10 w-44 rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 px-3 py-2 text-xs font-medium uppercase tracking-wider text-slate-400">Set status</div>
                    @foreach ($statuses as $s)
                        <button type="button"
                                wire:click="updateStatus({{ $room->id }}, '{{ $s }}')"
                                @click="openMenu = null"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                            {{ $s }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- minimal toast --}}
    <div x-data="{ msg: '', tone: 'ok' }"
         @toast.window="msg = $event.detail.message; tone = $event.detail.tone; setTimeout(() => msg = '', 2500)"
         x-show="msg" x-cloak x-transition
         :class="tone === 'warn' ? 'bg-amber-600' : 'bg-emerald-600'"
         class="fixed bottom-6 right-6 rounded-md px-4 py-2 text-sm font-medium text-white shadow-lg">
        <span x-text="msg"></span>
    </div>
</div>
