@php
    $companies = auth()->user()->accessibleCompanies()->get(['companies.id', 'companies.name']);
    $nav = [
        ['label' => 'Dashboard', 'route' => 'home', 'can' => 'dashboard.view', 'active' => 'home'],
        ['label' => 'Sales', 'route' => 'sales.index', 'can' => 'sales.view', 'active' => 'sales.*'],
        ['label' => 'Customers', 'route' => 'customers.index', 'can' => 'customers.view', 'active' => 'customers.*'],
        ['label' => 'Suppliers', 'route' => 'suppliers.index', 'can' => 'suppliers.view', 'active' => 'suppliers.*'],
        ['label' => 'Purchase orders', 'route' => 'purchase-orders.index', 'can' => 'purchase_orders.view', 'active' => 'purchase-orders.*'],
        ['label' => 'Finance', 'route' => 'finance.entries', 'can' => 'finance.view', 'active' => 'finance.*'],
        ['label' => 'Reports', 'route' => 'reports.index', 'can' => 'reports.view', 'active' => 'reports.*'],
        ['label' => 'Employees', 'route' => 'hr.employees', 'can' => 'employees.view', 'active' => 'hr.employees'],
        ['label' => 'Attendance', 'route' => 'hr.attendance', 'can' => 'attendance.manage', 'active' => 'hr.attendance'],
        ['label' => 'Leave', 'route' => 'hr.leave', 'can' => 'leave.request', 'active' => 'hr.leave'],
        ['label' => 'Products', 'route' => 'inventory.products', 'can' => 'inventory.view', 'active' => 'inventory.products'],
        ['label' => 'Categories', 'route' => 'inventory.categories', 'can' => 'inventory.view', 'active' => 'inventory.categories'],
        ['label' => 'Stock movements', 'route' => 'inventory.movements', 'can' => 'inventory.view', 'active' => 'inventory.movements'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $title ?? '') · {{ $currentCompany->name }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen" id="shell">
    <input type="checkbox" id="nav-toggle" class="peer hidden">
    <aside class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full bg-slate-900 text-slate-200 transition-transform peer-checked:translate-x-0 md:static md:translate-x-0">
        <div class="flex h-16 items-center gap-3 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 font-bold text-white">B</div>
            <span class="text-lg font-semibold text-white">{{ config('app.name') }}</span>
        </div>
        <nav class="mt-4 space-y-1 px-3" aria-label="Main">
            @foreach ($nav as $item)
                @can($item['can'])
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($item['active']) ? 'bg-slate-800 text-white' : 'hover:bg-slate-800' }}">
                        {{ $item['label'] }}
                    </a>
                @endcan
            @endforeach
            @if (auth()->user()->is_super_admin)
                <a href="/admin" class="flex items-center rounded-lg px-3 py-2 text-sm font-medium hover:bg-slate-800">Administration</a>
            @endif
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-8">
            <div class="flex items-center gap-3">
                <label for="nav-toggle" class="cursor-pointer rounded-lg p-2 hover:bg-slate-100 md:hidden" aria-label="Toggle navigation">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </label>
                <form method="POST" action="{{ route('company.switch') }}" class="flex items-center gap-2">
                    @csrf
                    <label for="company_id" class="hidden text-xs font-medium uppercase tracking-wide text-slate-500 sm:block">Company</label>
                    <select id="company_id" name="company_id" onchange="this.form.submit()"
                            class="rounded-lg border border-slate-300 bg-white py-1.5 pl-3 pr-8 text-sm font-medium">
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected($company->id === $currentCompany->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="rounded border px-2 py-1 text-sm">Switch</button></noscript>
                </form>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="hidden text-slate-600 sm:block">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">Sign out</button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-8">
            @if (session('status'))
                <p class="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ session('status') }}</p>
            @endif
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </main>
    </div>
</div>
</body>
</html>
