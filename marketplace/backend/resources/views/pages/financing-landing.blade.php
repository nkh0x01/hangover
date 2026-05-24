<x-layouts.app>
    <section class="bg-gradient-to-br from-brand-cream-100 via-white to-brand-gold-50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20">
            <div class="max-w-3xl">
                <span class="badge bg-brand-gold text-brand-ink mb-4">დაფინანსების მრჩეველი</span>
                <h1 class="font-display text-4xl lg:text-5xl text-brand-ink">იპოვე დაფინანსება შენი საქმისთვის</h1>
                <p class="mt-4 text-lg text-brand-ink/70">
                    შევსე მოკლე ანკეტა და მიიღე შენი ბიზნესისთვის შესაფერისი დაფინანსების პროგრამები — Enterprise Georgia, RDA, GITA, grants.gov.ge და სხვა.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('financing.questionnaire') }}" class="btn-primary">ანკეტის შევსება</a>
                    <a href="{{ route('financing.programs.index') }}" class="btn-secondary">პროგრამების კატალოგი</a>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 grid md:grid-cols-3 gap-8">
            <div class="card p-6">
                <div class="text-3xl text-brand-gold mb-3">1</div>
                <h3 class="font-display text-xl mb-2">შეავსე ანკეტა</h3>
                <p class="text-sm text-brand-ink/70">მოგვაწოდე ბიზნესის შესახებ ძირითადი ინფორმაცია: სექტორი, რეგიონი, წლიური ბრუნვა, ბიზნესის ხნოვანება.</p>
            </div>
            <div class="card p-6">
                <div class="text-3xl text-brand-gold mb-3">2</div>
                <h3 class="font-display text-xl mb-2">მიიღე რეკომენდაცია</h3>
                <p class="text-sm text-brand-ink/70">სისტემა მოგცემს ჩამონათვალს დაფინანსების პროგრამების შესაბამისობის პროცენტით.</p>
            </div>
            <div class="card p-6">
                <div class="text-3xl text-brand-gold mb-3">3</div>
                <h3 class="font-display text-xl mb-2">მოამზადე დოკუმენტები</h3>
                <p class="text-sm text-brand-ink/70">გენერირდება საჭირო საბუთების ჩამონათვალი, შემდეგ გადახვალ ოფიციალურ საიტზე ან მიიღებ კონსულტანტის დახმარებას.</p>
            </div>
        </div>
    </section>

    <section class="py-12 bg-brand-cream-50 border-y border-brand-cream-200">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm text-brand-ink/70">
                <strong class="text-brand-ink">მნიშვნელოვანი:</strong> სისტემა ავტომატურად არ აგზავნის განაცხადს სახელმწიფო პროგრამებში —
                ის გეხმარება პროგრამის შერჩევაში, საბუთების მომზადებასა და ოფიციალურ განაცხადზე გადასვლაში.
            </p>
        </div>
    </section>
</x-layouts.app>
