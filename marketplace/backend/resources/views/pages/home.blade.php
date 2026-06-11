<x-layouts.app>
    {{-- Hero --}}
    <section class="bg-gradient-to-br from-brand-cream-100 via-brand-cream-50 to-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20 lg:py-28 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="badge-verified mb-4">{{ __('badge.made_in_georgia') }}</span>
                <h1 class="font-display text-4xl lg:text-6xl text-brand-ink leading-tight">
                    {{ $hero?->title_ka ?? 'აღმოაჩინე ქართული წარმოების პროდუქტები ერთ სივრცეში' }}
                </h1>
                <p class="mt-6 text-lg text-brand-ink/70 max-w-xl">
                    {{ $hero?->subtitle_ka ?? 'შეიძინე ქართველი მცირე მეწარმეებისა და ადგილობრივი მწარმოებლების პროდუქცია' }}
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('catalog.index') }}" class="btn-primary">პროდუქტების დათვალიერება</a>
                    <a href="{{ route('seller.onboarding') }}" class="btn-secondary">გახდი მეწარმე</a>
                </div>
            </div>
            <div class="hidden lg:block">
                <div class="relative">
                    <div class="absolute -top-8 -left-8 w-72 h-72 bg-brand-red-50 rounded-full blur-3xl opacity-50"></div>
                    <div class="absolute -bottom-8 -right-8 w-72 h-72 bg-brand-gold-50 rounded-full blur-3xl opacity-50"></div>
                    <div class="relative grid grid-cols-2 gap-4">
                        @foreach ($featuredProducts->take(4) as $p)
                            <div class="card p-4 {{ $loop->iteration % 2 ? 'translate-y-4' : '' }}">
                                <div class="aspect-square bg-brand-cream-100 rounded-xl flex items-center justify-center">
                                    <span class="font-display text-3xl text-brand-gold-700 opacity-40">{{ mb_substr($p->title_ka, 0, 1) }}</span>
                                </div>
                                <h4 class="mt-3 text-sm font-semibold line-clamp-1">{{ $p->title_ka }}</h4>
                                <p class="text-xs text-brand-ink/60">{{ number_format($p->price_gel, 0) }} ₾</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust row --}}
    <section class="border-y border-brand-cream-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div>
                <div class="text-2xl">🇬🇪</div>
                <p class="mt-1 text-sm font-semibold">ქართული წარმოება</p>
                <p class="text-xs text-brand-ink/60">100% ადგილობრივი</p>
            </div>
            <div>
                <div class="text-2xl">✓</div>
                <p class="mt-1 text-sm font-semibold">დადასტურებული მეწარმე</p>
                <p class="text-xs text-brand-ink/60">გადამოწმებული მაღაზიები</p>
            </div>
            <div>
                <div class="text-2xl">📦</div>
                <p class="mt-1 text-sm font-semibold">გადახდა მიწოდებისას</p>
                <p class="text-xs text-brand-ink/60">უსაფრთხო შენაძენი</p>
            </div>
            <div>
                <div class="text-2xl">💼</div>
                <p class="mt-1 text-sm font-semibold">მცირე ბიზნესის მხარდაჭერა</p>
                <p class="text-xs text-brand-ink/60">დაფინანსების მრჩეველი</p>
            </div>
        </div>
    </section>

    {{-- Featured categories --}}
    <section class="py-16 bg-brand-cream-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="section-title gold-rule">პოპულარული კატეგორიები</h2>
            <div class="mt-10 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach ($categories as $cat)
                    <a href="{{ route('catalog.category', $cat->slug) }}" class="card p-5 text-center hover:bg-brand-red-50 transition">
                        <div class="font-display text-3xl text-brand-gold-700 mb-2">{{ mb_substr($cat->name_ka, 0, 1) }}</div>
                        <h3 class="text-sm font-semibold">{{ $cat->name_ka }}</h3>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured products --}}
    <section class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between">
                <h2 class="section-title gold-rule">ქართული წარმოების ახალი პროდუქტები</h2>
                <a href="{{ route('catalog.index') }}" class="text-sm font-medium text-brand-red-500 hover:underline hidden sm:inline">ყველას ნახვა →</a>
            </div>
            <div class="mt-10 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($featuredProducts as $p)
                    <x-product-card :product="$p"/>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured sellers --}}
    <section class="py-16 bg-brand-cream-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="section-title gold-rule">გაიცანი ქართველი მცირე მეწარმეები</h2>
            <div class="mt-10 grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($featuredSellers as $s)
                    <x-seller-card :seller="$s"/>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Financing CTA --}}
    <section class="py-20 bg-brand-ink text-white relative overflow-hidden">
        <div class="absolute inset-0 opacity-10 bg-gradient-to-tr from-brand-red-500 via-transparent to-brand-gold"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative">
            <div class="max-w-3xl mx-auto text-center">
                <span class="badge bg-brand-gold text-brand-ink mb-4">დაფინანსების მრჩეველი</span>
                <h2 class="font-display text-3xl md:text-5xl">იპოვე დაფინანსება შენი საქმისთვის</h2>
                <p class="mt-4 text-lg text-brand-cream-200">
                    შევსე მოკლე ანკეტა და მიიღე შენი ბიზნესისთვის შესაფერისი დაფინანსების პროგრამები — Enterprise Georgia, RDA, GITA, grants.gov.ge და სხვა.
                </p>
                <a href="{{ route('financing.questionnaire') }}" class="mt-8 btn bg-brand-red-500 text-white hover:bg-brand-red-600 inline-flex">
                    ანკეტის შევსება
                </a>
                <p class="mt-4 text-xs text-brand-cream-200/70">
                    სისტემა ავტომატურად არ აგზავნის განაცხადს — გეხმარება მომზადებაში.
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
