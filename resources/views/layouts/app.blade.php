<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Hotel PMS' }} — {{ config('app.name', 'Hotel PMS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
<div class="min-h-full lg:flex" x-data="{ sidebar: false }">

    {{-- Sidebar --}}
    <aside
        :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-40 w-60 transform bg-slate-900 text-slate-100 transition-transform duration-150 lg:static lg:translate-x-0">
        <div class="flex h-16 items-center justify-between px-5 border-b border-slate-800">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-wide text-white">
                Hotel PMS
            </a>
            <button type="button" class="lg:hidden text-slate-400" @click="sidebar = false">✕</button>
        </div>
        <nav class="px-3 py-4 space-y-1 text-sm">
            @php
                $links = [
                    ['route' => 'dashboard',          'label' => 'Dashboard',    'icon' => '🏠'],
                    ['route' => 'calendar',           'label' => 'Calendar',     'icon' => '📅'],
                    ['route' => 'reservations.index', 'label' => 'Reservations', 'icon' => '🧾'],
                    ['route' => 'rooms.index',        'label' => 'Rooms',        'icon' => '🛏'],
                    ['route' => 'guests.index',       'label' => 'Guests',       'icon' => '👤'],
                ];
            @endphp
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 rounded-md px-3 py-2 transition-colors
                          {{ request()->routeIs($link['route']) || request()->routeIs($link['route'].'.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <span class="text-base">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="absolute bottom-0 left-0 right-0 border-t border-slate-800 px-4 py-3 text-xs text-slate-400">
            <div class="font-medium text-slate-200">{{ auth()->user()?->name }}</div>
            <div class="truncate">{{ auth()->user()?->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="text-slate-400 hover:text-white">Sign out</button>
            </form>
        </div>
    </aside>

    {{-- Mobile backdrop --}}
    <div x-show="sidebar" x-transition.opacity class="fixed inset-0 z-30 bg-slate-900/70 backdrop-blur-sm lg:hidden" @click="sidebar = false" x-cloak></div>

    {{-- Main content --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 lg:px-8">
            <button type="button" class="lg:hidden text-slate-500" @click="sidebar = true">☰</button>
            <div class="text-lg font-semibold">{{ $header ?? ($title ?? '') }}</div>
            <div class="ml-auto flex items-center gap-3 text-sm text-slate-500">
                <button type="button"
                        @click="$dispatch('open-keyboard-help')"
                        class="hidden h-7 items-center gap-1.5 rounded-md border border-slate-200 px-2 text-xs text-slate-500 hover:border-slate-300 hover:text-slate-700 sm:inline-flex"
                        aria-label="Keyboard shortcuts">
                    <kbd class="font-mono">?</kbd>
                    <span class="hidden md:inline">Shortcuts</span>
                </button>
                <span>{{ now()->format('D, M j') }}</span>
            </div>
        </header>

        @if (session('status'))
            <div class="mx-4 mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700 lg:mx-8">
                {{ session('status') }}
            </div>
        @endif

        <main class="flex-1 p-4 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

<x-toast-stack />
<x-keyboard-help />

@livewireScripts
</body>
</html>
