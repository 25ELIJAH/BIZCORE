<div>
    <a href="{{ route('suppliers.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Suppliers</a>
    <div class="mb-6 mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">{{ $supplier->name }}</h1>
            <p class="text-sm text-slate-500">{{ $supplier->is_active ? 'Active supplier' : 'Inactive supplier' }}</p>
        </div>
        @can('purchase_orders.manage')
            @if ($supplier->is_active)<a href="{{ route('purchase-orders.create', ['supplier' => $supplier->id]) }}" class="btn-primary">New purchase order</a>@endif
        @endcan
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Outstanding balance</p><p class="mt-1 text-2xl font-semibold">{{ $currency }} {{ number_format((float) $balance, 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Open orders</p><p class="mt-1 text-2xl font-semibold">{{ $openOrders }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs uppercase tracking-wide text-slate-500">Total orders</p><p class="mt-1 text-2xl font-semibold">{{ $orders->total() }}</p></div>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">Contact person</dt><dd class="font-medium">{{ $supplier->contact_person ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ $supplier->email ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Phone</dt><dd class="font-medium">{{ $supplier->phone ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Tax / VAT number</dt><dd class="font-medium">{{ $supplier->tax_number ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">Address</dt><dd class="font-medium">{{ $supplier->address ?? '—' }}</dd></div>
            @if ($supplier->notes)<div class="sm:col-span-3"><dt class="text-slate-500">Notes</dt><dd>{{ $supplier->notes }}</dd></div>@endif
        </dl>
    </div>

    <h2 class="mb-3 text-lg font-semibold">Purchase history</h2>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr><th class="px-4 py-3">PO</th><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr wire:key="o-{{ $order->id }}">
                        <td class="px-4 py-3">@can('purchase_orders.view')<a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-indigo-600 hover:underline">{{ $order->po_number }}</a>@else{{ $order->po_number }}@endcan</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->order_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $order->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $order->balance(), 2) }}</td>
                        <td class="px-4 py-3"><x-purchasing.status :order="$order" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No purchase orders for this supplier yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</div>
