<div>
    <x-slot name="header">{{ __('Booking.com') }}</x-slot>

    <div class="mb-4 flex items-center gap-2">
        <a href="{{ route('channels.index') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Channels') }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Booking.com connections') }}</h3>

            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-2 py-2">{{ __('Name') }}</th>
                        <th class="px-2 py-2">{{ __('Mode') }}</th>
                        <th class="px-2 py-2">{{ __('Status') }}</th>
                        <th class="px-2 py-2">{{ __('Errors') }}</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($connections as $c)
                        <tr>
                            <td class="px-2 py-3 font-medium text-slate-800">{{ $c->name }}</td>
                            <td class="px-2 py-3">
                                @if ($c->isDryRun())
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs ring-1 ring-inset ring-amber-200 text-amber-800">{{ __('Dry-run') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs ring-1 ring-inset ring-rose-300 text-rose-800 font-semibold">{{ __('LIVE') }}</span>
                                @endif
                                @php $sandbox = data_get($c->settings, 'sandbox', true); @endphp
                                <span class="ml-1 text-xs text-slate-400">{{ $sandbox ? __('sandbox') : __('production') }}</span>
                            </td>
                            <td class="px-2 py-3 text-slate-600">{{ __(ucfirst($c->status)) }}</td>
                            <td class="px-2 py-3 tabular-nums {{ $c->error_count > 0 ? 'text-rose-600' : 'text-slate-600' }}">{{ $c->error_count }}</td>
                            <td class="px-2 py-3 text-right">
                                <a href="{{ route('channels.booking.show', $c) }}"
                                   class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-2 py-4 text-center italic text-slate-400">{{ __('No Booking.com connections yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">{{ __('New connection') }}</h3>
            <p class="text-xs text-slate-500 mb-3">
                {{ __('New Booking.com connections always start in DRY-RUN + SANDBOX mode. No outbound HTTP until you explicitly switch them live.') }}
            </p>
            <label class="block text-xs text-slate-500">{{ __('Connection name') }}</label>
            <input type="text" wire:model="newName" class="mt-1 w-full rounded-md border-slate-300 text-sm"
                   placeholder="e.g. Tbilisi Central · Booking">
            @error('newName')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <button wire:click="createConnection"
                    class="mt-3 w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">{{ __('Create') }}</button>

            <div class="mt-4 rounded-md bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                {{ __('Live pushes to a real Booking.com property are only enabled after credentials are saved and you flip the connection out of dry-run mode. Each live push still requires per-action confirmation.') }}
            </div>
        </div>
    </div>
</div>
