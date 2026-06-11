<x-layouts.app>
    <div class="bg-brand-cream-50 border-b border-brand-cream-200">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="section-title">{{ $pageTitle ?? 'პროდუქტების კატალოგი' }}</h1>
            @if ($categoryDesc ?? false)
                <p class="mt-2 text-brand-ink/70">{{ $categoryDesc }}</p>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid lg:grid-cols-[260px_1fr] gap-8">
            <aside class="space-y-6">
                <form method="GET" action="{{ url()->current() }}" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-brand-ink">ძიება</label>
                        <input type="search" name="q" value="{{ request('q') }}" class="mt-1 w-full rounded-lg border-brand-cream-200 focus:border-brand-red-500 focus:ring-brand-red-500" placeholder="რას ეძებ?">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-brand-ink">კატეგორია</label>
                        <select name="category" class="mt-1 w-full rounded-lg border-brand-cream-200">
                            <option value="">ყველა</option>
                            @foreach ($categories as $c)
                                <option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->name_ka }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-brand-ink">რეგიონი</label>
                        <select name="region" class="mt-1 w-full rounded-lg border-brand-cream-200">
                            <option value="">ყველა</option>
                            @foreach (config('marketplace.regions') as $key => $label)
                                <option value="{{ $key }}" @selected(request('region') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-sm font-medium text-brand-ink">მინ. ფასი</label>
                            <input type="number" name="min_price" value="{{ request('min_price') }}" min="0" class="mt-1 w-full rounded-lg border-brand-cream-200">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-brand-ink">მაქს. ფასი</label>
                            <input type="number" name="max_price" value="{{ request('max_price') }}" min="0" class="mt-1 w-full rounded-lg border-brand-cream-200">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary w-full">გაფილტვრა</button>
                    <a href="{{ url()->current() }}" class="block text-center text-sm text-brand-ink/60 hover:text-brand-red-500">გასუფთავება</a>
                </form>
            </aside>

            <div>
                <div class="flex items-center justify-between mb-6">
                    <p class="text-sm text-brand-ink/60">{{ $products->total() }} პროდუქტი</p>
                    <form method="GET">
                        @foreach (request()->except('sort', 'page') as $k => $v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endforeach
                        <select name="sort" onchange="this.form.submit()" class="rounded-lg border-brand-cream-200 text-sm">
                            <option value="newest" @selected(request('sort') === 'newest')>უახლესი</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>ფასი ↑</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>ფასი ↓</option>
                            <option value="rating" @selected(request('sort') === 'rating')>რეიტინგი</option>
                        </select>
                    </form>
                </div>

                @if ($products->count())
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        @foreach ($products as $p)
                            <x-product-card :product="$p"/>
                        @endforeach
                    </div>
                    <div class="mt-10">{{ $products->withQueryString()->links() }}</div>
                @else
                    <div class="card p-12 text-center">
                        <p class="text-brand-ink/60">პროდუქტი ვერ მოიძებნა — ცადე სხვა ფილტრი.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
