<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Sales</h1>
            <p class="text-sm text-slate-500">Invoices and their payment status.</p>
        </div>
        @can('sales.create')
            <a href="{{ route('sales.create') }}" class="btn-primary">New sale</a>
        @endcan
    </div>

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Invoice no. or customer" aria-label="Search sales" class="input lg:col-span-2">
        <select wire:model.live="status" aria-label="Sale status" class="input">
            <option value="all">All statuses</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
        </select>
        <select wire:model.live="payment" aria-label="Payment status" class="input">
            <option value="all">Any payment</option><option value="unpaid">Unpaid</option><option value="partial">Partially paid</option><option value="paid">Paid</option><option value="overdue">Overdue</option>
        </select>
        <input type="date" wire:model.live="from" aria-label="From date" class="input">
        <input type="date" wire:model.live="to" aria-label="To date" class="input">
        <select wire:model.live="rep" aria-label="Sales representative" class="input">
            <option value="">All sales reps</option>
            @foreach ($reps as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
        </select>
        <select wire:model.live="customer" aria-label="Customer" class="input lg:col-span-2">
            <option value="">All customers</option>
            @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Sales rep</th>
                    <th class="px-4 py-3 text-right">Total ({{ $currency }})</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3">Due</th><th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $sale)
                    <tr wire:key="sale-{{ $sale->id }}">
                        <td class="px-4 py-3"><a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $sale->invoice?->invoice_number }}</a></td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->sale_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $sale->customer?->name ?? 'Walk-in customer' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->user?->name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->balance(), 2) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->due_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3"><x-sales.status :sale="$sale" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No sales match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
</div>
