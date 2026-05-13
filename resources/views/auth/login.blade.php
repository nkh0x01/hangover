<x-guest-layout>
    <h1 class="text-2xl font-semibold text-slate-900">{{ __('Sign in') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('Use your reception account.') }}</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   value="{{ old('email') }}"
                   class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-slate-700 focus:ring-slate-500">
            {{ __('Keep me signed in') }}
        </label>

        <button type="submit"
                class="w-full rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
            {{ __('Sign in') }}
        </button>

        @if (Route::has('password.request'))
            <div class="text-center">
                <a href="{{ route('password.request') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('Forgot password?') }}</a>
            </div>
        @endif
    </form>

    {{-- Language switcher on the guest screen too --}}
    <div class="mt-6 flex justify-center gap-3 text-xs">
        @foreach (['ka' => 'ქართული', 'en' => 'English'] as $code => $label)
            <a href="{{ route('locale.switch', $code) }}"
               class="{{ app()->getLocale() === $code ? 'font-semibold text-slate-900' : 'text-slate-400 hover:text-slate-700' }}">{{ $label }}</a>
        @endforeach
    </div>
</x-guest-layout>
