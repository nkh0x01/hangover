<x-layouts.app>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">ჩემი შეკვეთები</h1>

        @if ($orders->isEmpty())
            <div class="mt-10 card p-12 text-center">
                <p class="text-brand-ink/60">ჯერ შეკვეთა არ გაგიკეთებიათ.</p>
            </div>
        @else
            <div class="mt-10 space-y-4">
                @foreach ($orders as $o)
                    <div class="card p-6 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold">#{{ $o->number }}</p>
                            <p class="text-xs text-brand-ink/60">{{ $o->placed_at?->format('d.m.Y H:i') }}</p>
                        </div>
                        <div class="text-sm">
                            @php
                                $statusLabels = ['pending'=>'მოლოდინში','confirmed'=>'დადასტურებული','packed'=>'შეფუთული','shipped'=>'გზაში','delivered'=>'მიწოდებული','cancelled'=>'გაუქმებული'];
                            @endphp
                            <span class="badge bg-brand-cream-200 text-brand-ink">{{ $statusLabels[$o->status] ?? $o->status }}</span>
                        </div>
                        <div class="font-semibold">{{ number_format($o->total_gel, 2) }} ₾</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-10">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.app>
