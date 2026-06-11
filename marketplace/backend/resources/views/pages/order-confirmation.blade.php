<x-layouts.app>
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-16 text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-brand-gold-50 text-brand-gold-700 text-3xl mb-6">✓</div>
        <h1 class="section-title">გმადლობთ შეკვეთისთვის!</h1>
        <p class="mt-3 text-brand-ink/70">თქვენი შეკვეთის ნომერია: <strong>{{ $order->number }}</strong></p>
        <p class="mt-2 text-sm text-brand-ink/60">გადახდის მეთოდი: გადახდა მიწოდებისას</p>

        <div class="mt-10 card p-6 text-left">
            <h2 class="font-display text-lg mb-4">შეკვეთის შინაარსი</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between">
                        <span>{{ $item->title_snapshot }} × {{ $item->quantity }}</span>
                        <span>{{ number_format($item->line_total_gel, 2) }} ₾</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 pt-4 border-t border-brand-cream-200 flex justify-between font-semibold">
                <span>სულ:</span>
                <span>{{ number_format($order->total_gel, 2) }} ₾</span>
            </div>
        </div>

        <a href="{{ route('account.orders') }}" class="mt-8 btn-primary inline-flex">ჩემი შეკვეთები</a>
    </div>
</x-layouts.app>
