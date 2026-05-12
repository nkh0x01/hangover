<div>
    <x-slot name="header">{{ __('Minibar — room :number', ['number' => $room->number]) }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('inventory.minibars') }}"
           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">{{ __('← Minibars') }}</a>
        <button wire:click="refill"
                wire:loading.attr="disabled" wire:target="refill"
                class="ml-auto inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
            <x-spinner wire:loading wire:target="refill" class="h-4 w-4 -ml-1" />
            {{ __('Refill from storage') }}
        </button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">{{ __('Product') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Par level') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('In room') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Diff') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php $diff = $item->current - $item->par_level; @endphp
                    <tr>
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $item->product?->name }}</td>
                        <td class="px-4 py-2 text-right">
                            <input type="number" min="0" max="99" value="{{ $item->par_level }}"
                                   wire:change="updatePar({{ $item->id }}, $event.target.value)"
                                   class="w-20 rounded-md border-slate-300 text-right">
                        </td>
                        <td class="px-4 py-2 text-right">{{ $item->current }}</td>
                        <td class="px-4 py-2 text-right {{ $diff < 0 ? 'text-amber-700 font-medium' : ($diff > 0 ? 'text-emerald-700' : '') }}">
                            {{ $diff > 0 ? '+'.$diff : $diff }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="removeItem({{ $item->id }})"
                                    class="text-xs text-slate-400 hover:text-red-600"
                                    onclick="return confirm('{{ __('Remove from minibar?') }}')">{{ __('Remove') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">{{ __('No products in this minibar yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add product --}}
    @if ($availableProducts->isNotEmpty())
        <div class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-xs font-medium text-slate-500 uppercase">{{ __('Add product') }}</label>
                <select wire:model="newProductId" class="mt-1 rounded-md border-slate-300 text-sm">
                    <option value="">{{ __('— Pick a product —') }}</option>
                    @foreach ($availableProducts as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 uppercase">{{ __('Par level') }}</label>
                <input type="number" min="0" max="99" wire:model="newParLevel"
                       class="mt-1 w-24 rounded-md border-slate-300 text-sm">
            </div>
            <button wire:click="addItem"
                    class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ __('+ Add to minibar') }}</button>
        </div>
    @endif
</div>
