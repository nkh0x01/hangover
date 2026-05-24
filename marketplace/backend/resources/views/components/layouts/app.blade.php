<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('marketplace.name.ka') }}</title>
    <meta name="description" content="{{ $description ?? 'ქართველი მცირე მეწარმეების და ადგილობრივი მწარმოებლების პროდუქცია' }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col">

    <header class="bg-white border-b border-brand-cream-200 sticky top-0 z-40">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="font-display text-xl font-bold text-brand-red-500">{{ config('marketplace.name.ka') }}</span>
                </a>

                <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-brand-ink">
                    <a href="{{ route('catalog.index') }}" class="hover:text-brand-red-500">კატალოგი</a>
                    <a href="{{ route('sellers.index') }}" class="hover:text-brand-red-500">მეწარმეები</a>
                    <a href="{{ route('financing.landing') }}" class="hover:text-brand-red-500">დაფინანსება</a>
                    <a href="{{ route('cms.page', 'about') }}" class="hover:text-brand-red-500">ჩვენ შესახებ</a>
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ route('cart.show') }}" class="text-brand-ink hover:text-brand-red-500 relative" aria-label="კალათა">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </a>
                    @auth
                        <a href="{{ route('account.orders') }}" class="text-sm text-brand-ink hover:text-brand-red-500 hidden sm:inline">{{ auth()->user()->name }}</a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-brand-ink hover:text-brand-red-500">გასვლა</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium hidden sm:inline hover:text-brand-red-500">შესვლა</a>
                        <a href="{{ route('register') }}" class="btn-primary text-sm py-2 px-4">რეგისტრაცია</a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        @if (session('status'))
            <div class="bg-brand-gold-50 text-brand-gold-700 border-b border-brand-gold-100">
                <div class="mx-auto max-w-7xl px-4 py-3 text-sm sm:px-6 lg:px-8">{{ session('status') }}</div>
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="bg-brand-ink text-brand-cream-100 mt-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 grid gap-8 md:grid-cols-4">
            <div>
                <h4 class="font-display text-xl text-white">{{ config('marketplace.name.ka') }}</h4>
                <p class="mt-2 text-sm text-brand-cream-200">ქართველი მცირე მეწარმეების და ადგილობრივი მწარმოებლების პროდუქცია</p>
            </div>
            <div>
                <h5 class="font-semibold text-white mb-3">პლატფორმა</h5>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('cms.page', 'about') }}" class="hover:text-white">ჩვენ შესახებ</a></li>
                    <li><a href="{{ route('cms.page', 'contact') }}" class="hover:text-white">კონტაქტი</a></li>
                </ul>
            </div>
            <div>
                <h5 class="font-semibold text-white mb-3">მყიდველებისთვის</h5>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('catalog.index') }}" class="hover:text-white">კატალოგი</a></li>
                    <li><a href="{{ route('cms.page', 'how-to-sell') }}" class="hover:text-white">როგორ ვიყიდო</a></li>
                </ul>
            </div>
            <div>
                <h5 class="font-semibold text-white mb-3">მეწარმეებისთვის</h5>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('cms.page', 'how-to-become-seller') }}" class="hover:text-white">გახდი მეწარმე</a></li>
                    <li><a href="{{ route('financing.landing') }}" class="hover:text-white">დაფინანსების მრჩეველი</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-brand-cream-200/10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 text-xs text-brand-cream-200">
                © {{ date('Y') }} {{ config('marketplace.name.ka') }} — ყველა უფლება დაცულია
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
