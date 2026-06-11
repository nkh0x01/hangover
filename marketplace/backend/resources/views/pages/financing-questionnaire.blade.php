<x-layouts.app>
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">დაფინანსების ანკეტა</h1>
        <p class="mt-4 text-brand-ink/70">შეავსე ეს მოკლე ანკეტა და მიიღე შენი ბიზნესისთვის შესაფერისი დაფინანსების პროგრამები.</p>

        <form method="POST" action="{{ route('financing.recommendations.post') }}" class="mt-8 card p-8 space-y-6">
            @csrf

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium">სექტორი *</label>
                    <select name="sector" required class="mt-1 w-full rounded-lg border-brand-cream-200">
                        @foreach (config('marketplace.seller_sectors') as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">რეგიონი *</label>
                    <select name="region" required class="mt-1 w-full rounded-lg border-brand-cream-200">
                        @foreach (config('marketplace.regions') as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">ბიზნესის ხნოვანება (თვე)</label>
                    <input type="number" name="business_age_months" min="0" max="600" value="12" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">წლიური ბრუნვა (GEL)</label>
                    <input type="number" name="annual_revenue_gel" min="0" value="30000" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">თანამშრომელთა რაოდენობა</label>
                    <input type="number" name="employees_count" min="0" max="500" value="1" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">სასურველი თანხა (GEL)</label>
                    <input type="number" name="funding_amount_gel" min="0" value="50000" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">თანადაფინანსების შესაძლებლობა (%)</label>
                    <input type="number" name="co_financing_pct" min="0" max="100" value="20" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">დაფინანსების მიზანი</label>
                    <select name="purpose" class="mt-1 w-full rounded-lg border-brand-cream-200">
                        <option value="">აირჩიე</option>
                        <option value="equipment">აღჭურვილობა</option>
                        <option value="materials">ნედლეული</option>
                        <option value="workspace">სამუშაო სივრცე</option>
                        <option value="export">ექსპორტი</option>
                        <option value="packaging">შესაფუთი</option>
                        <option value="marketing">მარკეტინგი</option>
                        <option value="training">ტრენინგი</option>
                        <option value="digitalization">დიგიტალიზაცია</option>
                    </select>
                </div>
            </div>

            <div class="border-t border-brand-cream-200 pt-6">
                <p class="text-sm font-medium mb-3">დამატებითი მახასიათებლები</p>
                <div class="grid md:grid-cols-2 gap-3 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_woman_owned" value="1" class="rounded text-brand-red-500"> ქალის მფლობელობაში მყოფი ბიზნესი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_youth_owned" value="1" class="rounded text-brand-red-500"> 35 წლამდე მფლობელი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_mountainous_region" value="1" class="rounded text-brand-red-500"> მაღალმთიანი რეგიონი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_startup" value="1" class="rounded text-brand-red-500"> სტარტაპი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_existing_business" value="1" checked class="rounded text-brand-red-500"> არსებული ბიზნესი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_agriculture" value="1" class="rounded text-brand-red-500"> სოფლის მეურნეობა</label>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">მიიღე რეკომენდაცია</button>
        </form>
    </div>
</x-layouts.app>
