<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">სისტემის დიაგნოსტიკა</x-slot>
            <x-slot name="description">
                უსაფრთხო, არასაიდუმლო ინფორმაცია ადმინისტრატორისთვის. აქ არ ჩანს API keys, certificates ან tokens.
            </x-slot>

            <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->diagnostics() as $label => $value)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-2 break-words text-sm font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
