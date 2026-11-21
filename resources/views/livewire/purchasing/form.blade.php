<div>
    <a href="{{ $order ? route('purchase-orders.show', $order) : route('purchase-orders.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ $order ? $order->po_number : 'Purchase orders' }}</a>
    <h1 class="mb-6 mt-2 text-2xl font-semibold">{{ $order ? 'Edit '.$order->po_number : 'New purchase order' }}</h1>

    @error('form')<p class="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 font-semibold">Items</h2>
                <div class="relative mb-4">
                    <input type="search" wire:model.live.debounce.250ms="productSearch" placeholder="Search a product by name or SKU to add it" aria-label="Add product" class="input">
                    @if ($results->isNotEmpty())
                        <ul class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                            @foreach ($results as $product)
                                <li wire:key="res-{{ $product->id }}">
                                    <button type="button" wire:click="addProduct({{ $product->id }})" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span><span class="font-medium">{{ $product->name }}</span> <span class="text-xs text-slate-500">{{ $product->sku }}</span></span>
                                        <span class="text-xs text-slate-500">cost {{ number_format((float) $product->purchase_price, 2) }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (trim($productSearch) !== '')
                        <p class="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500 shadow-lg">No matching active products.</p>
                    @endif
                </div>

                @error('lines')<p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

                @if ($lines)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr><th class="py-2 pr-3">Product</th><th class="px-3 py-2">Qty</th><th class="px-3 py-2">Unit cost</th><th class="px-3 py-2 text-right">Line total</th><th></th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($lines as $i => $line)
                                    <tr wire:key="line-{{ $line['product_id'] }}">
                                        <td class="py-2 pr-3"><div class="font-medium">{{ $line['name'] }}</div><div class="text-xs text-slate-500">{{ $line['sku'] }} · {{ $line['unit'] }}</div></td>
                                        <td class="px-3 py-2"><input type="number" step="0.001" min="0" wire:model.live.debounce.300ms="lines.{{ $i }}.quantity" aria-label="Quantity" class="input w-24">
                                            @error("lines.$i.quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                                        <td class="px-3 py-2"><input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="lines.{{ $i }}.unit_cost" aria-label="Unit cost" class="input w-28">
                                            @error("lines.$i.unit_cost")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                                        <td class="px-3 py-2 text-right">{{ is_numeric($line['quantity']) && is_numeric($line['unit_cost']) ? number_format((float) \App\Support\Money::mul($line['quantity'], \App\Support\Money::round($line['unit_cost'])), 2) : '—' }}</td>
                                        <td class="py-2 pl-3 text-right"><button type="button" wire:click="removeLine({{ $i }})" class="text-red-600 hover:underline">Remove</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="py-8 text-center text-sm text-slate-500">No items yet. Search above to add products.</p>
                @endif
            </section>

            <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">
                <x-ui.field label="Supplier" name="supplierId">
                    <select id="supplierId" wire:model="supplierId" class="input">
                        <option value="">Choose a supplier…</option>
                        @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Order date" name="orderDate"><input id="orderDate" type="date" wire:model="orderDate" class="input"></x-ui.field>
                <x-ui.field label="Expected delivery" name="expectedDate"><input id="expectedDate" type="date" wire:model="expectedDate" class="input"></x-ui.field>
                <x-ui.field label="Notes" name="notes"><input id="notes" type="text" wire:model="notes" class="input" maxlength="2000"></x-ui.field>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 font-semibold">Totals</h2>
                <x-ui.field label="Tax rate (%)" name="taxRate" class="mb-4"><input id="taxRate" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.300ms="taxRate" class="input"></x-ui.field>
                <dl class="space-y-1 border-t border-slate-100 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd>{{ number_format((float) $totals['subtotal'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Tax</dt><dd>{{ number_format((float) $totals['tax'], 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total ({{ $currency }})</dt><dd>{{ number_format((float) $totals['total'], 2) }}</dd></div>
                </dl>
            </section>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Save as draft</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </aside>
    </form>
</div>
