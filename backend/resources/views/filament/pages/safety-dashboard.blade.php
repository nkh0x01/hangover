<x-filament-panels::page>
    {{-- Header widget rendered by getHeaderWidgets() --}}

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-filament::section>
            <x-slot name="heading">Active SOS events</x-slot>
            <x-slot name="description">
                Page the on-call SRE within 60 s of any new event. Resolve
                only after a human conversation with the reporter.
            </x-slot>

            @php
                $open = \App\Modules\Support\Models\SosEvent::query()
                    ->with(['user', 'ride'])
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->latest()
                    ->limit(15)
                    ->get();
            @endphp

            @if ($open->isEmpty())
                <p class="text-sm text-gray-500">No active SOS events.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($open as $sos)
                        <li class="py-3 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-medium">#{{ $sos->id }}</span>
                                <span class="ml-2">{{ $sos->user->phone_e164 ?? '—' }}</span>
                                @if ($sos->ride)
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-800 font-mono">{{ substr($sos->ride->ulid, 0, 8) }}</span>
                                @endif
                                <span class="ml-2 text-xs px-2 py-0.5 rounded {{ $sos->status === 'open' ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-800' }}">{{ $sos->status }}</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ $sos->created_at?->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Driver verification queue</x-slot>
            <x-slot name="description">
                Drivers with documents in review. Click a row to open
                the driver record + document review actions.
            </x-slot>

            @php
                $inReview = \App\Modules\Driver\Models\Driver::query()
                    ->with('user')
                    ->where('verification_status', 'in_review')
                    ->orderBy('updated_at')
                    ->limit(15)
                    ->get();
            @endphp

            @if ($inReview->isEmpty())
                <p class="text-sm text-gray-500">No drivers awaiting review.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($inReview as $driver)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-medium">{{ $driver->user->phone_e164 ?? '—' }}</span>
                                <span class="ml-2 text-xs text-gray-500">since {{ $driver->updated_at?->diffForHumans() }}</span>
                            </div>
                            <a href="{{ \App\Modules\Driver\Filament\Resources\DriverResource::getUrl('view', ['record' => $driver]) }}" class="text-sm text-emerald-700 hover:underline">review →</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Open fraud flags</x-slot>
            <x-slot name="description">
                Automatic detections + admin-raised flags. Resolve once
                ops has investigated.
            </x-slot>

            @php
                $flags = \App\Modules\Support\Models\FraudFlag::query()
                    ->with('user')
                    ->whereNull('resolved_at')
                    ->orderByDesc('severity')
                    ->latest()
                    ->limit(15)
                    ->get();
            @endphp

            @if ($flags->isEmpty())
                <p class="text-sm text-gray-500">No open flags.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($flags as $flag)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-mono text-gray-500">#{{ $flag->id }}</span>
                                <span class="ml-2 text-xs px-2 py-0.5 rounded {{ $flag->severity === 'block' ? 'bg-red-50 text-red-700' : ($flag->severity === 'warn' ? 'bg-yellow-50 text-yellow-800' : 'bg-gray-100 text-gray-600') }}">{{ $flag->severity }}</span>
                                <span class="ml-2">{{ $flag->kind }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $flag->user->phone_e164 ?? '—' }}</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ $flag->created_at?->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Urgent complaints</x-slot>
            <x-slot name="description">
                Safety-category tickets jump to `urgent` automatically.
                Page Ops lead if any are unattended after 10 min.
            </x-slot>

            @php
                $urgent = \App\Modules\Support\Models\SupportTicket::query()
                    ->where('priority', 'urgent')
                    ->whereIn('status', ['open', 'in_progress', 'waiting_user'])
                    ->latest()
                    ->limit(15)
                    ->get();
            @endphp

            @if ($urgent->isEmpty())
                <p class="text-sm text-gray-500">No urgent tickets.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($urgent as $ticket)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-mono text-gray-500">#{{ $ticket->id }}</span>
                                <span class="ml-2">{{ $ticket->subject }}</span>
                                <span class="ml-2 text-xs px-2 py-0.5 rounded bg-red-50 text-red-700">{{ $ticket->category }}</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ $ticket->created_at?->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
