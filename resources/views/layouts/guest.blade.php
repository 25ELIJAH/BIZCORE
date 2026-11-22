<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0C4137">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    {{ Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    {{-- Brand panel --}}
    <aside class="hidden bg-brunswick p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-brand text-base font-bold text-brunswick">B</div>
            <span class="text-lg font-semibold">{{ config('app.name') }}</span>
        </div>
        <div class="max-w-md">
            <h2 class="text-3xl font-semibold leading-snug">Business management for every company you run.</h2>
            <ul class="mt-8 space-y-3 text-sm text-slate-300">
                <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-brand"></span>Sales, stock and purchasing kept in agreement</li>
                <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-brand"></span>Finance and reports in Kenyan shillings, ready to print or export</li>
                <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-brand"></span>Each company's data kept separate, with roles for your team</li>
            </ul>
        </div>
        <p class="text-xs text-slate-400">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </aside>

    {{-- Form panel --}}
    <main class="flex items-center justify-center bg-white px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-8">
                <div class="mb-6 flex items-center gap-2.5 lg:hidden">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brunswick text-sm font-bold text-emerald-brand">B</div>
                    <span class="text-base font-semibold">{{ config('app.name') }}</span>
                </div>
                <h1 class="text-2xl font-semibold">Sign in</h1>
                <p class="mt-1 text-sm text-slate-500">Enter your details to access your account.</p>
            </div>
            @if (session('status'))
                <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
