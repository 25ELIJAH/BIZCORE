<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Purchase orders</h1>
            <p class="text-sm text-slate-500">Orders to suppliers. Stock increases only when goods are received.</p>
        </div>
        @can('purchase_orders.manage')
            <a href="{{ route('purchase-orders.create') }}" class="btn-primary">New purchase order</a>
        @endcan
    </div>

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="PO number or supplier" aria-label="Search purchase orders" class="input lg:col-span-2">
        <select wire:model.live="status" aria-label="Status" class="input">
            <option value="all">All statuses</option>
            @foreach (\App\Models\PurchaseOrder::STATUS_LABELS as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
        </select>
        <input type="date" wire:model.live="from" aria-label="From date" class="input">
        <input type="date" wire:model.live="to" aria-label="To date" class="input">
        <select wire:model.live="supplier" aria-label="Supplier" class="input">
            <option value="">All suppliers</option>
            @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">PO</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3 text-right">Total ({{ $currency }})</th><th class="px-4 py-3 text-right">Balance</th><th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr wire:key="po-{{ $order->id }}">
                        <td class="px-4 py-3"><a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-indigo-600 hover:underline">{{ $order->po_number }}</a></td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->order_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $order->supplier?->name }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $order->balance(), 2) }}</td>
                        <td class="px-4 py-3"><x-purchasing.status :order="$order" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No purchase orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</div>
