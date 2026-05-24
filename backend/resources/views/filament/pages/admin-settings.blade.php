<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">პილოტის პარამეტრები</x-slot>
            <x-slot name="description">
                ეს გვერდი ამ ეტაპზე მხოლოდ კითხვის რეჟიმშია, რათა ადმინისტრატორმა სწრაფად დაინახოს მნიშვნელოვანი production კონფიგურაცია.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($this->settings() as $setting)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-950 dark:text-white">{{ $setting['label'] }}</div>
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $setting['help'] }}</div>
                            </div>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200' => $setting['ok'],
                                'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200' => ! $setting['ok'],
                            ])>
                                {{ $setting['ok'] ? 'OK' : 'Needs attention' }}
                            </span>
                        </div>
                        <div class="mt-4 rounded-lg bg-gray-50 px-3 py-2 font-mono text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                            {{ $setting['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
