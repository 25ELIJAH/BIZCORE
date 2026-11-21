<div>
    <a href="{{ route('customers.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Customers</a>
    <div class="mb-6 mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ $customer->name }}</h1>
            <p class="text-sm text-slate-500">{{ $customer->is_active ? 'Active customer' : 'Inactive customer' }}</p>
        </div>
        @can('sales.create')
            <a href="{{ route('sales.create', ['customer' => $customer->id]) }}" class="btn-primary">New sale</a>
        @endcan
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Outstanding balance</p><p class="mt-1 text-2xl font-semibold">{{ $currency }} {{ number_format((float) $balance, 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Overdue invoices</p><p class="mt-1 text-2xl font-semibold {{ $overdueCount ? 'text-red-600' : '' }}">{{ $overdueCount }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Total sales</p><p class="mt-1 text-2xl font-semibold">{{ $sales->total() }}</p></div>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">Contact person</dt><dd class="font-medium">{{ $customer->contact_person ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $customer->email ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $customer->phone ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Tax / VAT number</dt><dd class="font-medium">{{ $customer->tax_number ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">Address</dt><dd class="font-medium">{{ $customer->address ?? '—' }}</dd></div>
            @if ($customer->notes)<div class="sm:col-span-3"><dt class="text-slate-500">Notes</dt><dd>{{ $customer->notes }}</dd></div>@endif
        </dl>
    </div>

    <h2 class="mb-3 text-lg font-semibold">Transaction history</h2>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sales as $sale)
                    <tr wire:key="s-{{ $sale->id }}">
                        <td class="px-4 py-3"><a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $sale->invoice?->invoice_number }}</a></td>
                        <td class="px-4 py-3 text-slate-600">{{ $sale->sale_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->balance(), 2) }}</td>
                        <td class="px-4 py-3"><x-sales.status :sale="$sale" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No sales to this customer yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $sales->links() }}</div>
</div>
