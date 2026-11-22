<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0C4137">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-2">
    {{-- Brand panel --}}
    <aside class="relative hidden overflow-hidden bg-gradient-to-br from-ink via-brunswick to-indigo-600 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-brand/25 blur-3xl" style="animation: float-slow 9s ease-in-out infinite"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-emerald-brand/10 blur-3xl" style="animation: float-slow 11s ease-in-out infinite reverse"></div>
        <div class="relative flex items-center gap-3 animate-slide-in">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-brand to-indigo-600 text-lg font-bold text-ink shadow-lg shadow-emerald-brand/30">B</div>
            <span class="text-xl font-semibold tracking-tight">{{ config('app.name') }}</span>
        </div>
        <div class="relative max-w-md space-y-8" data-stagger>
            <h2 class="text-4xl font-semibold leading-tight tracking-tight">Run every company from one calm place.</h2>
            <ul class="space-y-4 text-slate-300">
                <li class="flex items-start gap-3"><span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-brand/20 text-emerald-brand">✓</span>Sales, stock and purchasing that always agree with each other</li>
                <li class="flex items-start gap-3"><span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-brand/20 text-emerald-brand">✓</span>Finance and reports in Kenyan shillings, ready to print or export</li>
                <li class="flex items-start gap-3"><span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-brand/20 text-emerald-brand">✓</span>Each company's data kept strictly separate, with roles for your team</li>
            </ul>
        </div>
        <p class="relative text-xs text-slate-500">Multi-company business management</p>
    </aside>

    {{-- Form panel --}}
    <main class="flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md animate-fade-up">
            <div class="mb-8 text-center lg:text-left">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-brand to-indigo-600 text-xl font-bold text-ink shadow-lg shadow-emerald-brand/20 lg:hidden">B</div>
                <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                <p class="mt-1 text-sm text-slate-500">Sign in to {{ config('app.name') }} to continue.</p>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-lift">
                @if (session('status'))
                    <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700" role="status">{{ session('status') }}</p>
                @endif
                @yield('content')
            </div>
        </div>
    </main>
</div>
</body>
</html>
