<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Live ride monitor</x-slot>
        <x-slot name="description">
            Auto-refreshes every {{ $this->pollingInterval }}. Click any
            row to drill into the ride. WebSocket-driven monitor lands in
            Phase 4.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
