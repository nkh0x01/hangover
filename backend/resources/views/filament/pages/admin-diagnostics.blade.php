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

        <x-filament::section>
            <x-slot name="heading">OTP SMS დიაგნოსტიკა</x-slot>
            <x-slot name="description">
                ბოლო sender.ge/OTP მცდელობები. კოდები და API keys აქ არ ინახება და არ ჩანს.
            </x-slot>

            @php
                $counts = $this->smsCounts();
            @endphp

            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Sent</div>
                    <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $counts['sent'] }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Failed/skipped</div>
                    <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $counts['failed'] }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total OTP attempts</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $counts['total'] }}</div>
                </div>
            </div>

            <div class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">დრო</th>
                            <th class="px-4 py-3">ტელეფონი</th>
                            <th class="px-4 py-3">მიზანი</th>
                            <th class="px-4 py-3">პროვაიდერი</th>
                            <th class="px-4 py-3">სტატუსი</th>
                            <th class="px-4 py-3">შეცდომა/მიზეზი</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-950">
                        @forelse ($this->recentSmsAttempts() as $attempt)
                            <tr>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $attempt['created_at'] }}</td>
                                <td class="px-4 py-3 font-mono text-gray-900 dark:text-white">{{ $attempt['phone'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $attempt['purpose'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $attempt['provider'] }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200' => $attempt['status'] === 'sent',
                                        'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-200' => $attempt['status'] !== 'sent',
                                    ])>{{ $attempt['status'] }}</span>
                                </td>
                                <td class="max-w-xl px-4 py-3 text-gray-600 dark:text-gray-400">{{ $attempt['error'] ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    OTP SMS მცდელობები ჯერ არ არის.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
