<x-layouts.app>
    <div class="mx-auto max-w-md px-4 sm:px-6 lg:px-8 py-16">
        <div class="card p-8">
            <h1 class="font-display text-2xl text-center">შესვლა</h1>

            @if ($errors->any())
                <div class="mt-4 p-3 bg-brand-red-50 text-brand-red-700 rounded-lg text-sm">
                    @foreach ($errors->all() as $err) <p>{{ $err }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="text-sm font-medium">ელფოსტა</label>
                    <input type="email" name="email" required autofocus value="{{ old('email') }}" class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <div>
                    <label class="text-sm font-medium">პაროლი</label>
                    <input type="password" name="password" required class="mt-1 w-full rounded-lg border-brand-cream-200">
                </div>
                <button type="submit" class="btn-primary w-full">შესვლა</button>
            </form>

            <p class="mt-6 text-sm text-center text-brand-ink/60">
                არ გაქვს ანგარიში? <a href="{{ route('register') }}" class="text-brand-red-500 hover:underline">დარეგისტრირდი</a>
            </p>
        </div>
    </div>
</x-layouts.app>
