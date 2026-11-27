@php
    $money = fn ($v) => number_format((float) $v, 2);
    $tile = 'rounded-xl border border-slate-200 bg-white p-4';
    $kpi = 'kpi';
    $label = 'text-xs font-medium text-slate-500';
@endphp
<div>
    <div class="mb-8 flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="{{ $label }}">Currently managing</p>
            <h1 class="mt-0.5">{{ $company->name }}</h1>
        </div>
        <p class="text-sm text-slate-500">Currency {{ $currency }} &middot; figures update each time you open this page.</p>
    </div>

    @livewire(\App\Livewire\Dashboard\Attention::class)

    @if ($data === [])
        <p class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">There is nothing to show on your dashboard yet. Use the menu to open the areas you have access to.</p>
    @endif

    {{-- Headline figures: the numbers an owner looks at first --}}
    @if (isset($data['sales']) || isset($data['finance']))
        <h2 class="mb-3 mt-2 text-[13px] font-semibold uppercase tracking-wide text-slate-900">Quick access <span class="font-normal normal-case tracking-normal text-slate-500">&middot; this month</span></h2>
        <div class="mb-9 grid gap-x-5 gap-y-4 sm:grid-cols-2 xl:grid-cols-3" >
            @isset($data['sales'])
                <div class="{{ $kpi }} is-primary"><p class="{{ $label }}">Sales this month</p><p class="mt-1.5 text-2xl font-semibold text-slate-900">{{ $currency }} <span data-count="{{ (float) $data['sales']['month_total'] }}" data-decimals="2">{{ $money($data['sales']['month_total']) }}</span></p><p class="mt-1 text-xs text-slate-500">{{ $data['sales']['month_count'] }} sale(s)</p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Customers owe you</p><p class="mt-1.5 text-2xl font-semibold text-slate-900">{{ $currency }} <span data-count="{{ (float) $data['sales']['receivable'] }}" data-decimals="2">{{ $money($data['sales']['receivable']) }}</span></p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Overdue invoices</p><p class="mt-1.5 text-2xl font-semibold text-slate-900 {{ $data['sales']['overdue_count'] ? 'text-red-600' : '' }}">{{ $data['sales']['overdue_count'] }}</p></div>
            @endisset
            @isset($data['finance'])
                <div class="{{ $kpi }}"><p class="{{ $label }}">Income</p><p class="mt-2 text-2xl font-semibold tracking-tight text-emerald-700">{{ $currency }} <span data-count="{{ (float) $data['finance']['income'] }}" data-decimals="2">{{ $money($data['finance']['income']) }}</span></p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Expenses</p><p class="mt-2 text-2xl font-semibold tracking-tight text-red-700">{{ $currency }} <span data-count="{{ (float) $data['finance']['expense'] }}" data-decimals="2">{{ $money($data['finance']['expense']) }}</span></p></div>
                <div class="{{ $kpi }}"><p class="{{ $label }}">Net</p><p class="mt-1.5 text-2xl font-semibold text-slate-900 {{ bccomp($data['finance']['net'], '0', 2) < 0 ? 'text-red-700' : 'text-slate-900' }}">{{ $currency }} <span data-count="{{ (float) $data['finance']['net'] }}" data-decimals="2">{{ $money($data['finance']['net']) }}</span></p></div>
            @endisset
        </div>
    @endif

    {{-- What needs the admin's attention right now --}}
    @php
        $aging = $data['sales']['aging'] ?? null;
        $agingTotal = $aging ? array_sum(array_map('floatval', $aging)) : 0;
        $buckets = $aging ? [
            ['Not yet due', $aging['current'], 'bg-slate-300'],
            ['1 – 30 days late', $aging['d30'], 'bg-orange-300'],
            ['31 – 60 days late', $aging['d60'], 'bg-orange-500'],
            ['Over 60 days late', $aging['d90'], 'bg-ink'],
        ] : [];
        $decisions = array_filter([
            isset($data['procurement']) && $data['procurement']['awaiting_approval'] ? ['Purchase orders awaiting approval', $data['procurement']['awaiting_approval'], route('purchase-orders.index')] : null,
            isset($data['hr']) && $data['hr']['pending_leave'] ? ['Leave requests awaiting a decision', $data['hr']['pending_leave'], route('hr.leave')] : null,
            isset($data['procurement']) && $data['procurement']['awaiting_delivery'] ? ['Purchase orders awaiting delivery', $data['procurement']['awaiting_delivery'], route('purchase-orders.index')] : null,
        ]);
    @endphp
    @if ($aging || isset($data['inventory']) || isset($data['procurement']) || isset($data['hr']))
        <h2 class="mb-3 mt-2 text-[13px] font-semibold uppercase tracking-wide text-slate-900">Needs your attention</h2>
        <div class="mb-9 grid gap-5 lg:grid-cols-2">
            @if ($aging)
                <section class="{{ $tile }}">
                    <h3 class="mb-1 text-sm font-semibold">Money customers owe, by age</h3>
                    <p class="mb-4 text-xs text-slate-500">Total outstanding {{ $currency }} {{ $money($agingTotal) }}</p>
                    <div class="mb-4 flex h-3 overflow-hidden rounded-full bg-slate-200" role="img" aria-label="Outstanding invoices by how late they are">
                        @foreach ($buckets as [$name, $amount, $colour])
                            @if ($agingTotal > 0 && (float) $amount > 0)<div class="{{ $colour }}" style="width: {{ round((float) $amount / $agingTotal * 100, 1) }}%"></div>@endif
                        @endforeach
                    </div>
                    <ul class="space-y-2 text-sm">
                        @foreach ($buckets as [$name, $amount, $colour])
                            <li class="flex items-center justify-between gap-3"><span class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-full {{ $colour }}"></span>{{ $name }}</span><strong>{{ $currency }} {{ $money($amount) }}</strong></li>
                        @endforeach
                    </ul>
                </section>
                <section class="{{ $tile }}">
                    <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-semibold">Largest overdue invoices</h3><a href="{{ route('sales.index') }}" class="text-xs font-medium text-slate-500 underline-offset-2 hover:underline">All sales</a></div>
                    @forelse ($data['sales']['overdue'] as $row)
                        <a href="{{ route('sales.show', $row['id']) }}" class="flex items-center justify-between gap-3 border-t border-slate-200 py-2.5 text-sm first:border-t-0 hover:text-slate-900">
                            <span class="min-w-0"><span class="block truncate font-medium">{{ $row['customer'] }}</span><span class="text-xs text-slate-500">{{ $row['number'] }} &middot; {{ $row['days'] }} day(s) late</span></span>
                            <strong class="whitespace-nowrap">{{ $currency }} {{ $money($row['balance']) }}</strong>
                        </a>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">No overdue invoices. Everything is on time.</p>
                    @endforelse
                </section>
            @endif
            @isset($data['inventory'])
                <section class="{{ $tile }}">
                    <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-semibold">Running low on stock</h3><a href="{{ route('inventory.products') }}" class="text-xs font-medium text-slate-500 underline-offset-2 hover:underline">All products</a></div>
                    @forelse ($data['inventory']['low_list'] as $row)
                        <div class="flex items-center justify-between gap-3 border-t border-slate-200 py-2.5 text-sm first:border-t-0">
                            <span class="min-w-0"><span class="block truncate font-medium">{{ $row['name'] }}</span><span class="text-xs text-slate-500">{{ $row['sku'] }}</span></span>
                            <span class="whitespace-nowrap text-right"><strong class="text-red-700">{{ rtrim(rtrim($row['quantity'], '0'), '.') ?: '0' }}</strong> <span class="text-xs text-slate-500">left &middot; reorder at {{ rtrim(rtrim($row['min'], '0'), '.') }}</span></span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Every product is above its reorder level.</p>
                    @endforelse
                </section>
            @endisset
            @if (isset($data['procurement']) || isset($data['hr']))
                <section class="{{ $tile }}">
                    <h3 class="mb-3 text-sm font-semibold">Waiting on a decision</h3>
                    @forelse ($decisions as [$name, $count, $url])
                        <a href="{{ $url }}" class="flex items-center justify-between gap-3 border-t border-slate-200 py-2.5 text-sm first:border-t-0 hover:text-slate-900"><span class="text-slate-600">{{ $name }}</span><span class="badge bg-ink text-white">{{ $count }}</span></a>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Nothing is waiting on you.</p>
                    @endforelse
                </section>
            @endif
        </div>
    @endif

    <h2 class="mb-3 mt-2 text-[13px] font-semibold uppercase tracking-wide text-slate-900">Operations</h2>
    <div class="grid gap-6 lg:grid-cols-3" >
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
