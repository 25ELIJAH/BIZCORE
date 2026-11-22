@php
    $companies = auth()->user()->accessibleCompanies()->get(['companies.id', 'companies.name']);
    // Heroicons (outline) path data, one icon per menu item.
    $icons = [
        'home' => 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'sales' => 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941',
        'customers' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
        'suppliers' => 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
        'orders' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z',
        'finance' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'reports' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        'employees' => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
        'attendance' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        'leave' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        'products' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'categories' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3zM6 6h.008v.008H6V6z',
        'movements' => 'M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5',
        'admin' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ];
    // Grouped menu: section label => items.
    $groups = [
        'Overview' => [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'home', 'can' => 'dashboard.view', 'active' => 'home'],
        ],
        'Trade' => [
            ['label' => 'Sales', 'icon' => 'sales', 'route' => 'sales.index', 'can' => 'sales.view', 'active' => 'sales.*'],
            ['label' => 'Customers', 'icon' => 'customers', 'route' => 'customers.index', 'can' => 'customers.view', 'active' => 'customers.*'],
            ['label' => 'Suppliers', 'icon' => 'suppliers', 'route' => 'suppliers.index', 'can' => 'suppliers.view', 'active' => 'suppliers.*'],
            ['label' => 'Purchase orders', 'icon' => 'orders', 'route' => 'purchase-orders.index', 'can' => 'purchase_orders.view', 'active' => 'purchase-orders.*'],
        ],
        'Money' => [
            ['label' => 'Finance', 'icon' => 'finance', 'route' => 'finance.entries', 'can' => 'finance.view', 'active' => 'finance.*'],
            ['label' => 'Reports', 'icon' => 'reports', 'route' => 'reports.index', 'can' => 'reports.view', 'active' => 'reports.*'],
        ],
        'People' => [
            ['label' => 'Employees', 'icon' => 'employees', 'route' => 'hr.employees', 'can' => 'employees.view', 'active' => 'hr.employees'],
            ['label' => 'Attendance', 'icon' => 'attendance', 'route' => 'hr.attendance', 'can' => 'attendance.manage', 'active' => 'hr.attendance'],
            ['label' => 'Leave', 'icon' => 'leave', 'route' => 'hr.leave', 'can' => 'leave.request', 'active' => 'hr.leave'],
        ],
        'Inventory' => [
            ['label' => 'Products', 'icon' => 'products', 'route' => 'inventory.products', 'can' => 'inventory.view', 'active' => 'inventory.products'],
            ['label' => 'Categories', 'icon' => 'categories', 'route' => 'inventory.categories', 'can' => 'inventory.view', 'active' => 'inventory.categories'],
            ['label' => 'Stock movements', 'icon' => 'movements', 'route' => 'inventory.movements', 'can' => 'inventory.view', 'active' => 'inventory.movements'],
        ],
    ];
    $initials = collect(preg_split('/\s+/', trim(auth()->user()->name)))->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563c9">
    <title>@yield('title', $title ?? '') · {{ $currentCompany->name }} · {{ config('app.name') }}</title>
    {{ Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="has-shell min-h-screen text-slate-800 antialiased">
<div id="busy-bar" class="busy-bar" aria-hidden="true"></div>
<svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false"><filter id="rail-goo" x="-60%" y="-8%" width="220%" height="116%" color-interpolation-filters="sRGB"><feGaussianBlur in="SourceGraphic" stdDeviation="7"/><feColorMatrix values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 22 -10"/></filter></svg>
<div class="min-h-screen lg:p-6">
<div class="mx-auto flex min-h-screen max-w-[1500px] flex-col overflow-hidden bg-white lg:min-h-[calc(100vh-3rem)] lg:rounded-[36px] lg:shadow-[var(--shadow-shell)]" id="shell">

    {{-- Top bar: logo, company switcher, user --}}
    <header class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 sm:h-20 sm:flex-nowrap sm:py-0 md:px-8">
        <label for="nav-toggle" class="cursor-pointer rounded-full p-2 text-slate-600 transition hover:bg-brand-soft lg:hidden" aria-label="Toggle navigation">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </label>
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-base font-bold text-white">B</span>
            <span class="text-xl font-bold text-indigo-600">{{ config('app.name') }}</span>
        </a>

        <form method="POST" action="{{ route('company.switch') }}" class="order-last flex w-full basis-full items-center sm:order-none sm:ml-2 sm:w-auto sm:min-w-0 sm:flex-1 sm:basis-auto">
            @csrf
            <label for="company_id" class="sr-only">Company</label>
            <div class="relative w-full max-w-md">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                <select id="company_id" name="company_id" onchange="this.form.submit()"
                        class="w-full appearance-none rounded-full border-0 bg-brand-soft py-2.5 pl-10 pr-10 text-sm font-medium text-slate-700 hover:bg-brand-tint focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600/25">
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected($company->id === $currentCompany->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </div>
            <noscript><button class="rounded border px-2 py-1 text-sm">Switch</button></noscript>
        </form>

        <div class="ml-auto flex shrink-0 items-center gap-3 text-sm sm:ml-0">
            <span class="hidden font-medium text-slate-700 sm:block">{{ auth()->user()->name }}</span>
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white" aria-hidden="true">{{ $initials }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-full p-2 text-slate-500 transition-colors hover:bg-brand-soft hover:text-indigo-600" title="Sign out" aria-label="Sign out">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                </button>
            </form>
        </div>
    </header>

    <div class="relative flex min-h-0 flex-1">
        <input type="checkbox" id="nav-toggle" class="peer hidden">
        <label for="nav-toggle" class="fixed inset-0 z-20 hidden bg-slate-900/40 peer-checked:block lg:peer-checked:hidden" aria-hidden="true"></label>

        {{-- Curved blue sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col overflow-y-auto bg-indigo-600 px-5 pb-5 pt-6 text-white transition-transform duration-200 peer-checked:translate-x-0 lg:static lg:z-20 lg:w-[112px] lg:shrink-0 lg:translate-x-0 lg:overflow-visible lg:bg-transparent lg:px-4 lg:pb-8 lg:pt-2">
            <div class="rail">
                <div class="rail-goo" aria-hidden="true"><div class="rail-shape"></div></div>
                <span class="rail-bubble" aria-hidden="true"></span>
            @can('sales.create')
                <a href="{{ route('sales.create') }}" data-label="New sale" class="rail-add mb-7 inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2.5 text-sm font-semibold text-indigo-600 transition-colors hover:bg-indigo-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    <span class="lg:sr-only">New sale</span>
                </a>
            @endcan
            <nav class="relative z-10 flex-1 space-y-5" aria-label="Main">
                @foreach ($groups as $group => $items)
                    @php $visible = collect($items)->filter(fn ($i) => auth()->user()->can($i['can'])); @endphp
                    @if ($visible->isNotEmpty())
                        <div>
                            <p class="mb-1.5 px-4 text-[10px] font-semibold uppercase tracking-[0.12em] text-white/50 lg:hidden">{{ $group }}</p>
                            <div class="space-y-0.5">
                                @foreach ($visible as $item)
                                    <a href="{{ route($item['route']) }}" class="nav-link group" data-label="{{ $item['label'] }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                                        <svg fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$item['icon']] }}"/></svg>
                                        <span class="nav-label">{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                @if (auth()->user()->is_super_admin)
                    <div>
                        <p class="mb-1.5 px-4 text-[10px] font-semibold uppercase tracking-[0.12em] text-white/50 lg:hidden">System</p>
                        <a href="/admin" data-label="Administration" class="nav-link group">
                            <svg fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons['admin'] }}"/></svg>
                            <span class="nav-label">Administration</span>
                        </a>
                    </div>
                @endif
            </nav>

            <div class="mt-6 border-t border-white/20 pt-4 text-xs lg:hidden">
                <p class="font-semibold uppercase tracking-wider text-white/60">Company</p>
                <p class="mt-1.5 truncate text-sm font-medium text-white">{{ $currentCompany->name }}</p>
                <p class="text-white/70">Currency {{ $currentCompany->currency }}</p>
            </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1 overflow-x-hidden px-4 pb-10 pt-4 md:px-10 md:pt-6">
            @if (session('status'))
                <p class="toast mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800" role="status" data-autohide>
                    <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    {{ session('status') }}
                </p>
            @endif
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </main>
    </div>
</div>
</div>
</body>
</html>
