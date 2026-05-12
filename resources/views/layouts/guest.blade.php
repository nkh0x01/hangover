<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — {{ config('app.name', 'Hotel PMS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-full">
    {{-- Brand panel --}}
    <div class="hidden w-1/2 bg-slate-900 lg:flex lg:flex-col lg:justify-between p-12 text-white">
        <div class="text-2xl font-bold tracking-wide">Hotel PMS</div>
        <div class="space-y-2">
            <div class="text-3xl font-semibold leading-tight">Reception, calmer.</div>
            <p class="text-slate-400 max-w-md">Book a room in under 30 seconds. Check in, check out, take payment — without leaving the keyboard.</p>
        </div>
        <div class="text-sm text-slate-500">© {{ now()->year }} Hotel PMS</div>
    </div>

    {{-- Form panel --}}
    <div class="flex-1 flex items-center justify-center p-6 lg:p-12">
        <div class="w-full max-w-sm bg-white rounded-xl shadow-sm border border-slate-200 p-8">
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
