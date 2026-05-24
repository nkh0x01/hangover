<x-layouts.app>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">დაფინანსების პროგრამები</h1>
        <p class="mt-2 text-brand-ink/70">სრული კატალოგი — შენ ან გაიარე ანკეტა <a href="{{ route('financing.questionnaire') }}" class="text-brand-red-500 hover:underline">აქ</a> ან დაათვალიერე ყველა პროგრამა.</p>

        <div class="mt-10 grid md:grid-cols-2 gap-4">
            @foreach ($programs as $p)
                <a href="{{ route('financing.programs.show', $p->slug) }}" class="card p-6 hover:shadow-card-lg transition">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-display text-lg">{{ $p->name_ka }}</h3>
                        @if ($p->is_demo)
                            <span class="badge bg-brand-red-50 text-brand-red-700 text-xs shrink-0">demo</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-brand-ink/70">{{ $p->summary_ka }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        <span class="badge-georgia">{{ str_replace('_', ' ', $p->provider) }}</span>
                        @if ($p->max_amount_gel)
                            <span class="badge bg-brand-gold-50 text-brand-gold-700">{{ number_format($p->max_amount_gel, 0) }} ₾-მდე</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-10">{{ $programs->links() }}</div>
    </div>
</x-layouts.app>
