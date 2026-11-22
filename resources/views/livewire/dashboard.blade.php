@php
    $money = fn ($v) => number_format((float) $v, 2);
    $tile = 'rounded-xl border border-slate-200 bg-white p-4';
    $kpi = 'kpi';
    $label = 'text-xs uppercase tracking-wide text-slate-500';
@endphp
<div>
    <div class="relative mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-brunswick to-indigo-600 p-6 text-white shadow-lift animate-fade-up">
        <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-emerald-brand/25 blur-3xl"></div>
        <p class="relative text-xs font-medium uppercase tracking-[0.14em] text-emerald-brand">Currently managing</p>
        <h1 class="relative mt-1 text-2xl font-semibold tracking-tight md:text-3xl">{{ $company->name }}</h1>
        <p class="relative mt-1 text-sm text-slate-300">Currency {{ $currency }} · figures update each time you open this page.</p>
    </div>

    @if ($data === [])
        <p class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">There is nothing to show on your dashboard yet. Use the menu to open the areas you have access to.</p>
    @endif

    {{-- Headline figures: the numbers an owner looks at first --}}
    @if (isset($data['sales']) || isset($data['finance']))
        <h2 class="mb-3 text-lg font-semibold tracking-tight text-slate-800">Key figures <span class="text-sm font-normal text-slate-500">· this month</span></h2>
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-stagger>
            @isset($data['sales'])
                <div class="{{ $kpi }}"><p class="{{ $label }}">Sales this month</p><p class="mt-2 text-2xl font-semibold tracking-tight xl:text-3xl">{{ $currency }} <span data-count="{{ (float) $data['sales']['month_total'] }}" data-decimals="2">{{ $money($data['sales']['month_total']) }}</span></p><p class="mt-1 text-xs text-slate-500">{{ $data['sales']['month_count'] }} sale(s)</p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Customers owe you</p><p class="mt-2 text-2xl font-semibold tracking-tight xl:text-3xl">{{ $currency }} <span data-count="{{ (float) $data['sales']['receivable'] }}" data-decimals="2">{{ $money($data['sales']['receivable']) }}</span></p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Overdue invoices</p><p class="mt-2 text-2xl font-semibold tracking-tight xl:text-3xl {{ $data['sales']['overdue_count'] ? 'text-red-600' : '' }}">{{ $data['sales']['overdue_count'] }}</p></div>
            @endisset
            @isset($data['finance'])
                <div class="{{ $kpi }}"><p class="{{ $label }}">Income</p><p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-700 xl:text-3xl">{{ $currency }} <span data-count="{{ (float) $data['finance']['income'] }}" data-decimals="2">{{ $money($data['finance']['income']) }}</span></p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Expenses</p><p class="mt-2 text-2xl font-semibold tracking-tight text-red-700 xl:text-3xl">{{ $currency }} <span data-count="{{ (float) $data['finance']['expense'] }}" data-decimals="2">{{ $money($data['finance']['expense']) }}</span></p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Net</p><p class="mt-2 text-2xl font-semibold tracking-tight xl:text-3xl {{ bccomp($data['finance']['net'], '0', 2) < 0 ? 'text-red-700' : 'text-brunswick' }}">{{ $currency }} <span data-count="{{ (float) $data['finance']['net'] }}" data-decimals="2">{{ $money($data['finance']['net']) }}</span></p></div>
            @endisset
        </div>
    @endif

    {{-- Trends --}}
    @if (isset($data['sales']) || isset($data['finance']))
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Trends</h2>
        <div class="mb-8 grid gap-4 lg:grid-cols-2">
            @isset($data['sales'])
                <section class="{{ $tile }}"><h3 class="mb-3 text-sm font-semibold">Sales, last 6 months</h3><div class="h-64" wire:ignore><canvas data-chart='@json($charts['sales'])' role="img" aria-label="Bar chart of monthly sales for the last six months"></canvas></div></section>
                <section class="{{ $tile }}"><h3 class="mb-3 text-sm font-semibold">Top products this month</h3>
                    @if ($data['sales']['top_products']['labels'])<div class="h-64" wire:ignore><canvas data-chart='@json($charts['top'])' role="img" aria-label="Bar chart of top selling products this month"></canvas></div>
                    @else<p class="py-16 text-center text-sm text-slate-500">No sales this month yet.</p>@endif
                </section>
            @endisset
            @isset($data['finance'])
                <section class="{{ $tile }} lg:col-span-2"><h3 class="mb-3 text-sm font-semibold">Income and expenses, last 6 months</h3><div class="h-64" wire:ignore><canvas data-chart='@json($charts['finance'])' role="img" aria-label="Bar chart comparing monthly income and expenses"></canvas></div></section>
            @endisset
        </div>
    @endif

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Operations</h2>
    <div class="grid gap-6 lg:grid-cols-3" data-stagger>
        @isset($data['inventory'])
            <section><h3 class="mb-2 text-sm font-semibold text-slate-700">Inventory</h3>
                <div class="{{ $tile }} space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Stock value</span><strong>{{ $currency }} {{ $money($data['inventory']['value']) }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Active products</span><strong>{{ $data['inventory']['products'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Low stock</span><strong class="{{ $data['inventory']['low_stock'] ? 'text-red-600' : '' }}">{{ $data['inventory']['low_stock'] }}</strong></div>
                </div></section>
        @endisset
        @isset($data['procurement'])
            <section><h3 class="mb-2 text-sm font-semibold text-slate-700">Procurement</h3>
                <div class="{{ $tile }} space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">You owe suppliers</span><strong>{{ $currency }} {{ $money($data['procurement']['payable']) }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Awaiting approval</span><strong>{{ $data['procurement']['awaiting_approval'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Awaiting delivery</span><strong>{{ $data['procurement']['awaiting_delivery'] }}</strong></div>
                </div></section>
        @endisset
        @isset($data['hr'])
            <section><h3 class="mb-2 text-sm font-semibold text-slate-700">People</h3>
                <div class="{{ $tile }} space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Active employees</span><strong>{{ $data['hr']['headcount'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">On leave today</span><strong>{{ $data['hr']['on_leave_today'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Leave awaiting decision</span><strong>{{ $data['hr']['pending_leave'] }}</strong></div>
                </div></section>
        @endisset
    </div>
</div>
