<div>
    <x-slot name="header">{{ __('Test connection') }}: {{ $connection->name }}</x-slot>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('channels.booking.show', $connection) }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Booking.com connection') }}</a>
    </div>

    <div class="max-w-2xl space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Test the Booking.com connection') }}</h3>
            <p class="text-sm text-slate-500">
                @if ($connection->isDryRun())
                    {{ __('In dry-run mode the test ALWAYS succeeds locally — no HTTP is sent. Disable dry-run to perform a real round-trip.') }}
                @else
                    {{ __('Calls the Booking.com /ping endpoint using the saved credentials.') }}
                @endif
            </p>

            <button wire:click="runTest"
                    class="mt-4 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                🔌 {{ __('Run test') }}
            </button>

            @if ($lastOk !== null)
                <div class="mt-4 rounded-md border px-3 py-3 text-sm
                            {{ $lastOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                    <div class="font-semibold">{{ $lastOk ? __('Success') : __('Failed') }}</div>
                    <div class="mt-1 text-xs">{{ $lastMessage }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ __('Ran at') }} {{ $lastRanAt }}</div>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('What this test does') }}</h3>
            <ul class="list-disc pl-5 text-sm text-slate-600 space-y-1">
                <li>{{ __('Verifies credentials are configured.') }}</li>
                <li>{{ __('Issues a no-op GET against /ping (dry-run skips the network call).') }}</li>
                <li>{{ __('Writes a sync log entry regardless of dry-run.') }}</li>
                <li>{{ __('Does NOT modify any state on Booking.com\'s side.') }}</li>
            </ul>
        </div>
    </div>
</div>
