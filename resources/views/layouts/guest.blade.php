<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#261a66">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}?v=3">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    {{ Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="has-shell min-h-screen text-slate-800 antialiased" @if ($errors->any()) data-shake @endif>
<div class="min-h-screen lg:flex lg:items-center lg:justify-center lg:p-6">
    <div class="auth-shell grid w-full max-w-5xl overflow-hidden bg-white lg:min-h-[600px] lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:rounded-[36px] lg:shadow-[var(--shadow-shell)]">
        {{-- Blue panel with the curved edge --}}
        <aside class="auth-panel hidden flex-col justify-between bg-ink p-10 text-white lg:flex lg:rounded-tr-[72px]">
            <span class="auth-orb a" aria-hidden="true"></span><span class="auth-orb b" aria-hidden="true"></span><span class="auth-orb c" aria-hidden="true"></span>
            <span class="auth-pulse" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="auth-chip one" aria-hidden="true"><span class="dot">&#10003;</span><span>Invoice paid<small>KES 48,500</small></span></span>
            <span class="auth-chip two" aria-hidden="true"><span class="dot">&#9650;</span><span>Sales up 12%<small>this month</small></span></span>
            <span class="auth-chip three" aria-hidden="true"><span class="dot">&#9632;</span><span>Stock in sync<small>all branches</small></span></span>
            <x-brand light size="lg" class="auth-logo" />
            <div>
                <h2 class="auth-rise text-3xl font-bold leading-snug" style="--i:0">Everything your business runs on, in <span class="auth-underline">one place.</span></h2>
                <ul class="mt-7 space-y-3 text-sm text-white/85">
                    <li class="auth-rise flex gap-3" style="--i:1"><span class="auth-bullet-dot mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-white" style="--i:1"></span>Sales, stock and purchasing kept in agreement</li>
                    <li class="auth-rise flex gap-3" style="--i:2"><span class="auth-bullet-dot mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-white" style="--i:2"></span>Finance and reports in Kenyan shillings</li>
                    <li class="auth-rise flex gap-3" style="--i:3"><span class="auth-bullet-dot mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-white" style="--i:3"></span>Each company's data kept separate, with roles for your team</li>
                </ul>
            </div>
            <p class="auth-rise text-xs text-white/60" style="--i:5">&copy; {{ date('Y') }} BIZCORE &middot; ERP Management System</p>
        </aside>

        <main class="flex items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-sm">
                <div class="auth-head mb-8">
                    <x-brand class="auth-logo mb-6 lg:hidden" />
                    <h1 class="text-3xl font-bold text-indigo-600">@yield('heading', 'Sign in')</h1>
                    <p class="mt-1 text-sm text-slate-500">@yield('subheading', 'Enter your details to access your account.')</p>
                </div>
                @if (session('status'))
                    <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</div>
</body>
</html>
