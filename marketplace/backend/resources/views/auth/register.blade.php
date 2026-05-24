<x-layouts.app>
    <div class="mx-auto max-w-md px-4 sm:px-6 lg:px-8 py-16">
        <div class="card p-8">
            <h1 class="font-display text-2xl text-center">რეგისტრაცია</h1>

            @if ($errors->any())
                <div class="mt-4 p-3 bg-brand-red-50 text-brand-red-700 rounded-lg text-sm">
                    @foreach ($errors->all() as $err) <p>{{ $err }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="text-sm font-medium">სახელი</label>
                    <input type="text" name="name" required autofocus value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">ელფოსტა</label>
                    <input type="email" name="email" required value="{{ old('email') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">ტელეფონი</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">პაროლი</label>
                    <input type="password" name="password" required minlength="8" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">გაიმეორე პაროლი</label>
                    <input type="password" name="password_confirmation" required minlength="8" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <button type="submit" class="btn-primary w-full">რეგისტრაცია</button>
            </form>

            <p class="mt-6 text-sm text-center text-brand-ink/60">
                უკვე გაქვს ანგარიში? <a href="{{ route('login') }}" class="text-brand-red-500 hover:underline">შესვლა</a>
            </p>
        </div>
    </div>
</x-layouts.app>
