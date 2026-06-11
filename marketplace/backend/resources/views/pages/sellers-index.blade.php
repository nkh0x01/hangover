<x-layouts.app>
    <div class="bg-brand-cream-50 border-b border-brand-cream-200">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
            <h1 class="section-title">ქართველი მცირე მეწარმეები</h1>
            <p class="mt-2 text-brand-ink/70">გაიცანი ჩვენი მაღაზიები — ადგილობრივი მწარმოებლები საქართველოს ყველა კუთხიდან</p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($sellers as $s)
                <x-seller-card :seller="$s"/>
            @endforeach
        </div>
        <div class="mt-10">{{ $sellers->links() }}</div>
    </div>
</x-layouts.app>
