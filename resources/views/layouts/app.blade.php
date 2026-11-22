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
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', $title ?? '') · {{ $currentCompany->name }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-slate-800 antialiased">
<div id="busy-bar" class="busy-bar" aria-hidden="true"></div>
<div class="flex min-h-screen" id="shell">
    <input type="checkbox" id="nav-toggle" class="peer hidden">
    <label for="nav-toggle" class="fixed inset-0 z-20 hidden bg-slate-900/50 backdrop-blur-sm peer-checked:block md:peer-checked:hidden" aria-hidden="true"></label>
    <aside class="fixed inset-y-0 left-0 z-30 flex w-64 -translate-x-full flex-col bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 text-slate-200 shadow-xl transition-transform duration-300 ease-out peer-checked:translate-x-0 md:sticky md:top-0 md:h-screen md:translate-x-0 md:shadow-none">
        <div class="flex h-16 shrink-0 items-center gap-3 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-400 to-indigo-600 font-bold text-white shadow-lg shadow-indigo-500/30">B</div>
            <div class="leading-tight">
                <span class="block text-base font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
                <span class="block max-w-[10rem] truncate text-[11px] text-slate-400">{{ $currentCompany->name }}</span>
            </div>
        </div>
        <nav class="mt-2 flex-1 space-y-5 overflow-y-auto px-3 pb-6" aria-label="Main">
            @foreach ($groups as $group => $items)
                @php $visible = collect($items)->filter(fn ($i) => auth()->user()->can($i['can'])); @endphp
                @if ($visible->isNotEmpty())
                    <div>
                        <p class="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $group }}</p>
                        <div class="space-y-0.5">
                            @foreach ($visible as $item)
                                <a href="{{ route($item['route']) }}" class="nav-link group" @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                                    <svg fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$item['icon']] }}"/></svg>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
            @if (auth()->user()->is_super_admin)
                <div>
                    <p class="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">System</p>
                    <a href="/admin" class="nav-link group">
                        <svg fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons['admin'] }}"/></svg>
                        Administration
                    </a>
                </div>
            @endif
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur-md md:px-8">
            <div class="flex items-center gap-3">
                <label for="nav-toggle" class="cursor-pointer rounded-lg p-2 transition hover:bg-slate-100 md:hidden" aria-label="Toggle navigation">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </label>
                <form method="POST" action="{{ route('company.switch') }}" class="flex items-center gap-2">
                    @csrf
                    <label for="company_id" class="hidden text-xs font-medium uppercase tracking-wide text-slate-500 sm:block">Company</label>
                    <select id="company_id" name="company_id" onchange="this.form.submit()"
                            class="rounded-lg border border-slate-300 bg-white py-1.5 pl-3 pr-8 text-sm font-medium shadow-xs transition hover:border-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-100">
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected($company->id === $currentCompany->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="rounded border px-2 py-1 text-sm">Switch</button></noscript>
                </form>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <div class="hidden items-center gap-2.5 sm:flex">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 text-xs font-semibold text-white shadow-sm" aria-hidden="true">{{ $initials }}</span>
                    <span class="font-medium text-slate-700">{{ auth()->user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-secondary !px-3 !py-1.5">Sign out</button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-8">
            @if (session('status'))
                <p class="toast mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 shadow-card" role="status" data-autohide>
                    <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    {{ session('status') }}
                </p>
            @endif
            <div class="animate-fade-in">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </div>
        </main>
    </div>
</div>
</body>
</html>
