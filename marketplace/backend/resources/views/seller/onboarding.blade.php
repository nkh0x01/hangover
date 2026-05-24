<x-layouts.app>
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="section-title gold-rule">გახდი მეწარმე</h1>
        <p class="mt-3 text-brand-ink/70">შეავსე მაღაზიის ბიზნეს პროფილი — დადასტურების შემდეგ შეძლებ პროდუქტების დამატებას.</p>

        @if ($errors->any())
            <div class="mt-4 p-3 bg-brand-red-50 text-brand-red-700 rounded-lg text-sm">
                @foreach ($errors->all() as $err) <p>{{ $err }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('seller.onboarding.submit') }}" class="mt-8 card p-8 space-y-5">
            @csrf

            <div>
                <label class="text-sm font-medium">მაღაზიის სახელი *</label>
                <input type="text" name="business_name" required value="{{ old('business_name') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium">სამართლებრივი ფორმა *</label>
                    <select name="legal_form" required class="mt-1 w-full rounded-lg border-brand-cream-200">
                        @foreach (config('marketplace.legal_forms') as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
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
                    <label class="text-sm font-medium">მუნიციპალიტეტი</label>
                    <input type="text" name="municipality" value="{{ old('municipality') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">ბიზნესის ხნოვანება (თვე)</label>
                    <input type="number" name="business_age_months" min="0" value="{{ old('business_age_months', 0) }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">თანამშრომელთა რაოდენობა</label>
                    <input type="number" name="employees_count" min="0" value="{{ old('employees_count', 1) }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
            </div>

            <div>
                <label class="text-sm font-medium">მეწარმის ისტორია</label>
                <textarea name="story" rows="5" class="mt-1 w-full rounded-lg border-brand-cream-200" placeholder="მოგვიყევი შენი ბიზნესის ისტორია — ეს დაეხმარება მყიდველებს ნდობა ჩამოაყალიბონ.">{{ old('story') }}</textarea>
            </div>

            <div class="border-t border-brand-cream-200 pt-5">
                <p class="text-sm font-medium mb-3">დამატებითი მახასიათებლები</p>
                <div class="grid md:grid-cols-2 gap-3 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_woman_owned" value="1" class="rounded text-brand-red-500"> ქალის მფლობელობა</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_youth_owned" value="1" class="rounded text-brand-red-500"> 35-მდე მფლობელი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_mountainous_region" value="1" class="rounded text-brand-red-500"> მაღალმთიანი რეგიონი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_startup" value="1" class="rounded text-brand-red-500"> სტარტაპი</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_agriculture" value="1" class="rounded text-brand-red-500"> სოფლის მეურნეობა</label>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">წარდგენა დადასტურებისთვის</button>
        </form>
    </div>
</x-layouts.app>
