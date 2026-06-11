<x-layouts.app>
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-10">
        <a href="{{ route('financing.programs.index') }}" class="text-sm text-brand-ink/60 hover:text-brand-red-500">← უკან</a>

        <div class="mt-4 flex items-start justify-between flex-wrap gap-3">
            <h1 class="section-title">{{ $program->name_ka }}</h1>
            @if ($program->is_demo)
                <span class="badge bg-brand-red-50 text-brand-red-700">demo ჩანაწერი — გადაამოწმე ოფიციალურ წყაროზე</span>
            @endif
        </div>

        <p class="mt-3 text-brand-ink/70">{{ $program->summary_ka }}</p>

        <div class="mt-6 card p-6 grid md:grid-cols-2 gap-4 text-sm">
            <div><span class="text-brand-ink/60">მიმწოდებელი:</span> <strong>{{ $program->provider }}</strong></div>
            <div><span class="text-brand-ink/60">ტიპი:</span> <strong>{{ $program->program_type }}</strong></div>
            @if ($program->min_amount_gel)
                <div><span class="text-brand-ink/60">მინ. თანხა:</span> <strong>{{ number_format($program->min_amount_gel, 0) }} ₾</strong></div>
            @endif
            @if ($program->max_amount_gel)
                <div><span class="text-brand-ink/60">მაქს. თანხა:</span> <strong>{{ number_format($program->max_amount_gel, 0) }} ₾</strong></div>
            @endif
            @if ($program->co_financing_required_pct)
                <div><span class="text-brand-ink/60">თანადაფინანსება:</span> <strong>{{ $program->co_financing_required_pct }}%</strong></div>
            @endif
            @if ($program->closes_at)
                <div><span class="text-brand-ink/60">დახურვა:</span> <strong>{{ $program->closes_at->format('d.m.Y') }}</strong></div>
            @endif
        </div>

        <div class="mt-6 prose prose-sm max-w-none">
            {!! nl2br(e($program->description_ka)) !!}
        </div>

        <section class="mt-8 card p-6">
            <h2 class="font-display text-xl mb-4">საჭირო დოკუმენტები</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($checklist as $item)
                    <li class="flex items-start gap-2">
                        <span class="text-brand-gold">{{ $item['required'] ? '●' : '○' }}</span>
                        <span>{{ $item['label_ka'] }}@if ($item['required']) <span class="text-brand-red-500 text-xs">სავალდებულო</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="mt-8 flex flex-wrap gap-3">
            @if ($program->application_url)
                <a href="{{ $program->application_url }}" target="_blank" rel="noopener" class="btn-primary">გადადი ოფიციალურ საიტზე ↗</a>
            @endif
            @auth
                <form method="POST" action="{{ route('financing.applications.start') }}">
                    @csrf
                    <input type="hidden" name="funding_program_id" value="{{ $program->id }}">
                    <button type="submit" class="btn-secondary">დაიწყე განაცხადის მომზადება</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-secondary">დაიწყე განაცხადის მომზადება (საჭიროა ავტორიზაცია)</a>
            @endauth
        </div>
    </div>
</x-layouts.app>
