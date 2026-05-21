<x-filament-panels::page>
    {{-- Header widget rendered above by getHeaderWidgets() --}}

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-filament::section>
            <x-slot name="heading">Today — test rides</x-slot>
            <x-slot name="description">
                Filtered to is_test_ride = true. Use this lane to sanity-
                check the pilot path before turning on real customers.
            </x-slot>

            @php
                $testRides = \App\Modules\Riding\Models\Ride::query()
                    ->where('is_test_ride', true)
                    ->where('requested_at', '>=', now()->startOfDay())
                    ->latest('requested_at')
                    ->limit(10)
                    ->get();
            @endphp

            @if ($testRides->isEmpty())
                <p class="text-sm text-gray-500">No test rides yet today.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($testRides as $ride)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-mono text-gray-500">{{ substr($ride->ulid, 0, 8) }}</span>
                                <span class="ml-3">{{ $ride->status->value }}</span>
                                @if ($ride->pilot_cohort)
                                    <span class="ml-2 inline-flex px-2 py-0.5 rounded text-xs bg-blue-50 text-blue-700">
                                        {{ $ride->pilot_cohort }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $ride->requested_at?->diffForHumans() }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Pilot readiness flags</x-slot>
            <x-slot name="description">
                Boolean checks driven by config/pilot.php. Flip in .env
                or via the operational dashboard once each item is verified.
            </x-slot>

            @php
                $checks = [
                    'PILOT_ENABLED' => (bool) config('pilot.enabled'),
                    'PILOT_COHORT set' => (bool) config('pilot.cohort'),
                    'Test phones registered' => count((array) config('pilot.test_phone_numbers', [])) > 0,
                    'Service hours configured' => (bool) config('pilot.service_hours.open')
                        && (bool) config('pilot.service_hours.close'),
                ];
            @endphp

            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($checks as $label => $ok)
                    <li class="py-2 flex items-center justify-between text-sm">
                        <span>{{ $label }}</span>
                        @if ($ok)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">
                                ✓ ready
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-red-100 text-red-800">
                                ✗ pending
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
