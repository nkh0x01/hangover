<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">Ride 360 ადმინისტრაცია</p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">პილოტის ოპერაციული პანელი</h2>
                    <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                        სწრაფი ხედვა მძღოლების განაცხადებზე, მგზავრობებზე და კონფიგურაციის მზადყოფნაზე.
                        ტექნიკური დეტალები გადატანილია დიაგნოსტიკის განყოფილებაში.
                    </p>
                </div>

                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <div class="font-medium text-gray-950 dark:text-white">{{ filament()->auth()->user()?->getFilamentName() ?? 'Admin' }}</div>
                    <div>{{ now()->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                </div>
            </div>
        </section>

        @php
            $warnings = $this->readinessWarnings();
        @endphp
        @if ($warnings !== [])
            <x-filament::section>
                <x-slot name="heading">ყურადღება სჭირდება</x-slot>
                <x-slot name="description">ეს საკითხები პილოტის მუშაობაზე შეიძლება აისახოს.</x-slot>

                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($warnings as $warning)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            {{ $warning }}
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->healthCards() as $card)
                <div class="rounded-xl border p-5 {{ $this->toneClass($card['tone']) }}">
                    <div class="text-sm font-medium">{{ $card['label'] }}</div>
                    <div class="mt-3 text-3xl font-semibold">{{ $card['value'] }}</div>
                    <div class="mt-2 text-sm opacity-80">{{ $card['description'] }}</div>
                </div>
            @endforeach
        </div>

        <x-filament::section>
            <x-slot name="heading">სწრაფი ბმულები</x-slot>
            <x-slot name="description">ყველაზე ხშირად გამოყენებული ადმინისტრაციული გვერდები.</x-slot>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->quickLinks() as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="group rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:bg-primary-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500/50 dark:hover:bg-primary-500/10"
                    >
                        <div class="flex items-start gap-3">
                            <x-dynamic-component :component="$link['icon']" class="mt-0.5 h-5 w-5 text-primary-600 dark:text-primary-400" />
                            <div>
                                <div class="font-medium text-gray-950 group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">
                                    {{ $link['label'] }}
                                </div>
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $link['description'] }}
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">ბოლო მძღოლის განაცხადები</x-slot>
                <x-slot name="description">ახალი რეგისტრაციები Driver აპიდან.</x-slot>

                @php
                    $applications = $this->recentApplications();
                @endphp
                @if ($applications === [])
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        განაცხადები ჯერ არ არის.
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">მძღოლი</th>
                                    <th class="px-4 py-3">ქალაქი</th>
                                    <th class="px-4 py-3">სტატუსი</th>
                                    <th class="px-4 py-3">თარიღი</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @foreach ($applications as $application)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-950 dark:text-white">
                                                {{ trim(($application->first_name ?? '') . ' ' . ($application->last_name ?? '')) ?: 'სახელი არ არის' }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ $application->phone_e164 ?? $application->user?->phone_e164 ?? '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $application->city?->name ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                {{ \App\Modules\Driver\Filament\Resources\DriverApplicationResource::statusOptions()[$application->status] ?? $application->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500">{{ $application->submitted_at?->diffForHumans() ?? $application->updated_at?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">ბოლო მგზავრობები</x-slot>
                <x-slot name="description">პილოტის ბოლო მოთხოვნები და მგზავრობის სტატუსები.</x-slot>

                @php
                    $rides = $this->recentRides();
                @endphp
                @if ($rides === [])
                    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        მგზავრობები ჯერ არ არის.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($rides as $ride)
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-mono text-sm font-medium text-gray-950 dark:text-white">{{ substr((string) $ride->ulid, 0, 10) }}</div>
                                        <div class="text-xs text-gray-500">{{ $ride->customer?->phone_e164 ?? 'მომხმარებელი არ არის' }}</div>
                                    </div>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ is_object($ride->status) ? $ride->status->value : $ride->status }}
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-gray-500">{{ $ride->requested_at?->diffForHumans() }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">დიაგნოსტიკა</x-slot>
            <x-slot name="description">ტექნიკური ინფორმაციაა, ამიტომ მთავარ ხედში დამალულია.</x-slot>

            <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($this->diagnostics() as $label => $value)
                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-2 break-words text-sm font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
