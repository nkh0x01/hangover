<div>
    <x-slot name="header">{{ __('Products') }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search by name, SKU, barcode…') }}"
               class="w-72 rounded-md border-slate-300 text-sm">
        <button wire:click="openCreate"
                class="ml-auto rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">{{ __('+ New product') }}</button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">{{ __('Name') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('SKU') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Category') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Cost') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Sale') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Stock') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Status') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $p)
                    @php $low = (int) ($p->total_stock ?? 0) <= $p->low_stock_threshold; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-900">{{ $p->name }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $p->sku ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-700">{{ $p->category?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $p->cost_price, 2) }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $p->sale_price, 2) }}</td>
                        <td class="px-4 py-2 text-right {{ $low ? 'text-red-600 font-medium' : '' }}">{{ (int) ($p->total_stock ?? 0) }}</td>
                        <td class="px-4 py-2">
                            @if ($p->active)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 ring-1 ring-emerald-200">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 ring-1 ring-slate-200">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="openEdit({{ $p->id }})" class="text-sm text-slate-500 hover:text-slate-800">{{ __('Edit') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">{{ __('No products yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>

    {{-- Form modal --}}
    <div x-cloak x-show="$wire.showForm"
         @keydown.window.escape="$wire.showForm = false"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div x-show="$wire.showForm"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.outside="$wire.showForm = false"
             class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-slate-900">{{ $editingId ? __('Edit product') : __('+ New product') }}</h3>
                <span class="text-xs text-slate-400">{{ __('Esc to close') }}</span>
            </div>
            @if ($error)
                <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700">{{ $error }}</div>
            @endif
            <div class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                    <input wire:model="name" class="mt-1 w-full rounded-md border-slate-300">
                    @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('SKU') }}</label>
                    <input wire:model="sku" class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Barcode') }}</label>
                    <input wire:model="barcode" class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Category') }}</label>
                    <select wire:model="categoryId" class="mt-1 w-full rounded-md border-slate-300">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Low stock threshold') }}</label>
                    <input type="number" min="0" wire:model="lowStockThreshold" class="mt-1 w-full rounded-md border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Cost price') }}</label>
                    <input type="number" step="0.01" wire:model="costPrice" class="mt-1 w-full rounded-md border-slate-300">
                    @error('costPrice') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Sale price') }}</label>
                    <input type="number" step="0.01" wire:model="salePrice" class="mt-1 w-full rounded-md border-slate-300">
                    @error('salePrice') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="trackStock" class="rounded border-slate-300">
                    {{ __('Track stock') }}
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="active" class="rounded border-slate-300">
                    {{ __('Active') }}
                </label>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button wire:click="$set('showForm', false)" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ __('Cancel') }}</button>
                <button wire:click="save"
                        wire:loading.attr="disabled" wire:target="save"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                    <x-spinner wire:loading wire:target="save" class="h-4 w-4 -ml-1" />
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>
</div>
