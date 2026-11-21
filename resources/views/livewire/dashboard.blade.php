@php
    $money = fn ($v) => number_format((float) $v, 2);
    $tile = 'rounded-xl border border-slate-200 bg-white p-4';
    $label = 'text-xs uppercase tracking-wide text-slate-500';
@endphp
<div>
    <div class="mb-6">
        <p class="{{ $label }}">Currently managing</p>
        <h1 class="text-2xl font-semibold">{{ $company->name }}</h1>
        <p class="text-sm text-slate-500">Currency {{ $currency }} · figures update each time you open this page.</p>
    </div>

    @if ($data === [])
        <p class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">There is nothing to show on your dashboard yet. Use the menu to open the areas you have access to.</p>
    @endif

    @isset($data['sales'])
        <h2 class="mb-2 text-sm font-semibold text-slate-700">Sales</h2>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="{{ $tile }}"><p class="{{ $label }}">Sales this month</p><p class="mt-1 text-2xl font-semibold">{{ $currency }} {{ $money($data['sales']['month_total']) }}</p><p class="text-xs text-slate-500">{{ $data['sales']['month_count'] }} sale(s)</p></div>
            <div class="{{ $tile }}"><p class="{{ $label }}">Customers owe you</p><p class="mt-1 text-2xl font-semibold">{{ $currency }} {{ $money($data['sales']['receivable']) }}</p></div>
            <div class="{{ $tile }}"><p class="{{ $label }}">Overdue invoices</p><p class="mt-1 text-2xl font-semibold {{ $data['sales']['overdue_count'] ? 'text-red-600' : '' }}">{{ $data['sales']['overdue_count'] }}</p></div>
        </div>
        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <section class="{{ $tile }}"><h3 class="mb-3 text-sm font-semibold">Sales, last 6 months</h3><div class="h-64" wire:ignore><canvas data-chart='@json($charts['sales'])' role="img" aria-label="Bar chart of monthly sales for the last six months"></canvas></div></section>
            <section class="{{ $tile }}"><h3 class="mb-3 text-sm font-semibold">Top products this month</h3>
                @if ($data['sales']['top_products']['labels'])<div class="h-64" wire:ignore><canvas data-chart='@json($charts['top'])' role="img" aria-label="Bar chart of top selling products this month"></canvas></div>
                @else<p class="py-16 text-center text-sm text-slate-500">No sales this month yet.</p>@endif
            </section>
        </div>
    @endisset

    @isset($data['finance'])
        <h2 class="mb-2 text-sm font-semibold text-slate-700">Finance (this month)</h2>
        <div class="mb-4 grid gap-4 sm:grid-cols-3">
            <div class="{{ $tile }}"><p class="{{ $label }}">Income</p><p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $currency }} {{ $money($data['finance']['income']) }}</p></div>
            <div class="{{ $tile }}"><p class="{{ $label }}">Expenses</p><p class="mt-1 text-2xl font-semibold text-red-700">{{ $currency }} {{ $money($data['finance']['expense']) }}</p></div>
            <div class="{{ $tile }}"><p class="{{ $label }}">Net</p><p class="mt-1 text-2xl font-semibold {{ bccomp($data['finance']['net'], '0', 2) < 0 ? 'text-red-700' : '' }}">{{ $currency }} {{ $money($data['finance']['net']) }}</p></div>
        </div>
        <section class="{{ $tile }} mb-6"><h3 class="mb-3 text-sm font-semibold">Income and expenses, last 6 months</h3><div class="h-64" wire:ignore><canvas data-chart='@json($charts['finance'])' role="img" aria-label="Bar chart comparing monthly income and expenses"></canvas></div></section>
    @endisset

    <div class="grid gap-6 lg:grid-cols-3">
        @isset($data['inventory'])
            <section><h2 class="mb-2 text-sm font-semibold text-slate-700">Inventory</h2>
                <div class="{{ $tile }} space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Stock value</span><strong>{{ $currency }} {{ $money($data['inventory']['value']) }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Active products</span><strong>{{ $data['inventory']['products'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Low stock</span><strong class="{{ $data['inventory']['low_stock'] ? 'text-red-600' : '' }}">{{ $data['inventory']['low_stock'] }}</strong></div>
                </div></section>
        @endisset
        @isset($data['procurement'])
            <section><h2 class="mb-2 text-sm font-semibold text-slate-700">Procurement</h2>
                <div class="{{ $tile }} space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">You owe suppliers</span><strong>{{ $currency }} {{ $money($data['procurement']['payable']) }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Awaiting approval</span><strong>{{ $data['procurement']['awaiting_approval'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Awaiting delivery</span><strong>{{ $data['procurement']['awaiting_delivery'] }}</strong></div>
                </div></section>
        @endisset
        @isset($data['hr'])
            <section><h2 class="mb-2 text-sm font-semibold text-slate-700">People</h2>
                <div class="{{ $tile }} space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Active employees</span><strong>{{ $data['hr']['headcount'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">On leave today</span><strong>{{ $data['hr']['on_leave_today'] }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-600">Leave awaiting decision</span><strong>{{ $data['hr']['pending_leave'] }}</strong></div>
                </div></section>
        @endisset
    </div>
</div>
