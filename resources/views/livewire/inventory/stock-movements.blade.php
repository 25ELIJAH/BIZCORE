<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Stock movements</h1>
        <p class="text-sm text-slate-500">Every change to stock quantities, with who made it and why.</p>
    </div>

    <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
        <select wire:model.live="product" aria-label="Product" class="input">
            <option value="">All products</option>
            @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>@endforeach
        </select>
        <select wire:model.live="type" aria-label="Movement type" class="input">
            <option value="">All types</option>
            @foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
        </select>
        <input type="date" wire:model.live="from" aria-label="From date" class="input">
        <input type="date" wire:model.live="to" aria-label="To date" class="input">
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th><th class="px-4 py-3">Product</th><th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Change</th><th class="px-4 py-3 text-right">Before</th><th class="px-4 py-3 text-right">After</th>
                    <th class="px-4 py-3">By</th><th class="px-4 py-3">Reason</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movements as $m)
                    <tr wire:key="mv-{{ $m->id }}">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3"><span class="font-medium">{{ $m->product->name }}</span> <span class="text-xs text-slate-500">{{ $m->product->sku }}</span></td>
                        <td class="px-4 py-3">{{ $m->type->label() }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $m->quantity >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $m->quantity >= 0 ? '+' : '' }}{{ rtrim(rtrim($m->quantity, '0'), '.') }}</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ rtrim(rtrim($m->previous_quantity, '0'), '.') ?: '0' }}</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ rtrim(rtrim($m->new_quantity, '0'), '.') ?: '0' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $m->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $m->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No stock movements match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
</div>
