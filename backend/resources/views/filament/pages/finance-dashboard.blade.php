<x-filament-panels::page>
    {{-- Header widget rendered by getHeaderWidgets() --}}

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-filament::section>
            <x-slot name="heading">Recent failed payments</x-slot>
            <x-slot name="description">
                Captured by the SettleRidePayment action. Each row needs
                a manual review — open the ride, decide whether to retry,
                refund, or write off.
            </x-slot>

            @php
                $failed = \App\Modules\Payment\Models\Payment::query()
                    ->where('status', 'failed')
                    ->latest()
                    ->limit(10)
                    ->get();
            @endphp

            @if ($failed->isEmpty())
                <p class="text-sm text-gray-500">No failed payments in the last 7 days.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($failed as $payment)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <div>
                                <span class="font-mono text-gray-500">#{{ $payment->id }}</span>
                                <span class="ml-2">{{ $payment->amount }} {{ $payment->currency }}</span>
                                <span class="ml-2 text-xs px-2 py-0.5 rounded bg-red-50 text-red-700">{{ $payment->failure_code ?? 'failed' }}</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ $payment->created_at?->diffForHumans() }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Active payment routing</x-slot>
            <x-slot name="description">
                Where each payment method is currently dispatched. Change
                via `.env` or `config/payment.php`.
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                @foreach (config('payment.methods', []) as $method => $gateway)
                    @php
                        $class = (string) config("payment.gateways.{$gateway}.class");
                        $configured = match ($gateway) {
                            'stripe' => (bool) config('payment.gateways.stripe.secret_key'),
                            'bog' => (bool) config('payment.gateways.bog.client_id'),
                            'tbc_pay' => (bool) config('payment.gateways.tbc_pay.api_key'),
                            'cash', 'wallet', 'null' => true,
                            default => false,
                        };
                    @endphp
                    <li class="py-2 flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ $method }}</span>
                            <span class="ml-2 text-gray-500">→ {{ $gateway }}</span>
                        </div>
                        @if ($configured)
                            <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-800">configured</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">no creds</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
