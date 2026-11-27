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
<body class="has-shell auth-body min-h-screen text-slate-800 antialiased" style="--auth-bg: url('{{ asset('images/auth-bg.svg') }}')" @if ($errors->any()) data-shake @endif>
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <main class="auth-shell auth-glass w-full max-w-md">
        <div class="auth-head mb-7 text-center">
            <x-brand light class="auth-logo mx-auto mb-5 justify-center" />
            <h1 class="text-3xl font-bold">@yield('heading', 'Sign in')</h1>
            <p class="mt-1.5 text-sm text-slate-500">@yield('subheading', 'Enter your details to access your account.')</p>
        </div>
        @if (session('status'))
            <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <p class="auth-foot">&copy; {{ date('Y') }} BIZCORE &middot; Sales, stock and accounts from one login</p>
</div>
</body>
</html>
