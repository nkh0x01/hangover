<x-filament-panels::page>
    {{-- Single-window cashier panel: cart, payment, shift — no context switching. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ZONE A + C: cart and scan --}}
        <section class="lg:col-span-2 space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">კალათა</h2>
                <span class="text-sm text-gray-500">
                    ცვლა:
                    @if ($this->shiftId)
                        <span class="font-medium text-success-600">#{{ $this->shiftId }} (ღია)</span>
                    @else
                        <span class="font-medium text-danger-600">დახურულია</span>
                    @endif
                </span>
            </div>

            <form wire:submit.prevent="scan" class="flex gap-2">
                <input
                    type="text"
                    wire:model="barcode"
                    autofocus
                    placeholder="დაასკანერეთ barcode / QR…"
                    class="flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                />
                <x-filament::button type="submit">დამატება</x-filament::button>
            </form>

            <table class="w-full text-sm">
                <thead class="text-left text-gray-500">
                    <tr>
                        <th class="py-2">SKU</th>
                        <th class="py-2 text-right">რაოდ.</th>
                        <th class="py-2 text-right">ფასი ₾</th>
                        <th class="py-2 text-right">სულ ₾</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->cart as $index => $line)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2">{{ $line['label'] }}</td>
                            <td class="py-2 text-right">{{ $line['qty'] }}</td>
                            <td class="py-2 text-right">{{ number_format($line['unit_price'], 2) }}</td>
                            <td class="py-2 text-right">{{ number_format($line['unit_price'] * $line['qty'], 2) }}</td>
                            <td class="py-2 text-right">
                                <button wire:click="removeLine({{ $index }})" class="text-danger-600">✕</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-400">კალათა ცარიელია</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- ZONE B: actions / payment / shift --}}
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-800">
                <p class="text-sm text-gray-500">ჯამი</p>
                <p class="text-3xl font-bold text-gray-950 dark:text-white">₾ {{ number_format($this->cartTotal(), 2) }}</p>
            </div>

            @if ($this->shiftId)
                <div class="grid grid-cols-1 gap-2">
                    <x-filament::button wire:click="pay('cash')" color="success" size="lg">
                        ნაღდი [F9]
                    </x-filament::button>
                    <x-filament::button wire:click="pay('card')" color="primary" size="lg">
                        ბარათი
                    </x-filament::button>
                </div>

                <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
                    <label class="text-sm text-gray-500">დათვლილი ნაღდი (Z)</label>
                    <input type="number" step="0.01" wire:model="countedCash"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" />
                    <x-filament::button wire:click="closeShift" color="danger" class="mt-2 w-full">
                        ცვლის დახურვა (Z report) [F1]
                    </x-filament::button>
                </div>
            @else
                <div>
                    <label class="text-sm text-gray-500">საწყისი ნაღდი</label>
                    <input type="number" step="0.01" wire:model="openingCash"
                        class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" />
                    <x-filament::button wire:click="openShift" color="success" class="mt-2 w-full">
                        ცვლის გახსნა [F1]
                    </x-filament::button>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
