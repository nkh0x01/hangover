<x-layouts.app>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">შენთვის შესაფერისი დაფინანსების პროგრამები</h1>
        <p class="mt-2 text-brand-ink/70">სისტემამ შენი პროფილზე დაყრდნობით შეარჩია ეს პროგრამები. ბმულზე გადასვლა მოგცემს მეტ ინფორმაციას.</p>

        <div class="mt-3 inline-block badge bg-brand-cream-200 text-brand-ink/70">
            სისტემა ავტომატურად არ აგზავნის განაცხადს — გეხმარება მომზადებაში
        </div>

        @if (count($recommendations) === 0)
            <div class="mt-10 card p-12 text-center">
                <p>ვერ ვიპოვეთ შესაბამისი პროგრამა — ცადე ფილტრის შესწორება ან <a href="{{ route('financing.programs.index') }}" class="text-brand-red-500 hover:underline">დაათვალიერე სრული კატალოგი</a>.</p>
            </div>
        @else
            <div class="mt-10 space-y-4">
                @foreach ($recommendations as $r)
                    <div class="card p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-brand-gold to-brand-red-500 text-white flex items-center justify-center font-display text-xl shrink-0">
                                {{ $r['match_percentage'] }}%
                            </div>
                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div>
                                        <h3 class="font-display text-lg">{{ $r['program']['name_ka'] }}</h3>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            <span class="badge-georgia">{{ str_replace('_', ' ', $r['program']['provider']) }}</span>
                                            <span class="badge bg-brand-gold-50 text-brand-gold-700">
                                                @php $types = ['grant'=>'გრანტი','subsidized_loan'=>'შეღავათიანი სესხი','training'=>'ტრენინგი','mixed'=>'შერეული','coaching'=>'კონსულტაცია']; @endphp
                                                {{ $types[$r['program']['program_type']] ?? $r['program']['program_type'] }}
                                            </span>
                                            @if ($r['program']['is_demo'])
                                                <span class="badge bg-brand-red-50 text-brand-red-700 text-xs">demo</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        @if ($r['program']['max_amount_gel'])
                                            <p class="font-display text-lg">{{ number_format($r['program']['max_amount_gel'], 0) }} ₾</p>
                                            <p class="text-xs text-brand-ink/60">მაქს. თანხა</p>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-3 text-sm text-brand-ink/70">{{ $r['program']['summary_ka'] }}</p>

                                @if (! empty($r['matched_rules']))
                                    <details class="mt-3 text-sm" open>
                                        <summary class="cursor-pointer font-medium text-brand-gold-700">✓ შესაბამისი ({{ count($r['matched_rules']) }})</summary>
                                        <ul class="mt-2 list-disc list-inside text-brand-ink/70 space-y-0.5">
                                            @foreach ($r['matched_rules'] as $m)
                                                <li>{{ $m }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif

                                @if (! empty($r['missing_requirements']))
                                    <details class="mt-2 text-sm">
                                        <summary class="cursor-pointer font-medium text-brand-red-600">⚠ გასათვალისწინებელი ({{ count($r['missing_requirements']) }})</summary>
                                        <ul class="mt-2 list-disc list-inside text-brand-ink/70 space-y-0.5">
                                            @foreach ($r['missing_requirements'] as $m)
                                                <li>{{ $m }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif

                                <p class="mt-4 text-sm text-brand-ink/80">→ {{ $r['suggested_next_step_ka'] }}</p>

                                <div class="mt-5 flex flex-wrap gap-3">
                                    @if ($r['program']['application_url'])
                                        <a href="{{ $r['program']['application_url'] }}" target="_blank" rel="noopener" class="btn-primary text-sm py-2">ოფიციალური საიტი ↗</a>
                                    @endif
                                    <a href="{{ route('financing.programs.show', $r['program']['slug']) }}" class="btn-secondary text-sm py-2">დეტალურად</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
