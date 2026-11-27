<div>
    <a href="{{ route('sales.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Sales</a>
    <h1 class="mb-6 mt-2 text-2xl font-semibold">New sale</h1>

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
                                        <span class="text-xs text-slate-500">{{ rtrim(rtrim($product->quantity, '0'), '.') ?: '0' }} {{ $product->unit }} · {{ number_format((float) $product->selling_price, 2) }}</span>
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
                                <tr><th class="py-2 pr-3">Product</th><th class="px-3 py-2">Qty</th><th class="px-3 py-2">Unit price</th><th class="px-3 py-2 text-right">Line total</th><th></th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($lines as $i => $line)
                                    <tr wire:key="line-{{ $line['product_id'] }}">
                                        <td class="py-2 pr-3">
                                            <div class="font-medium">{{ $line['name'] }}</div>
                                            <div class="text-xs {{ ! $allowNegative && is_numeric($line['quantity']) && (float) $line['quantity'] > (float) $line['available'] ? 'text-red-600' : 'text-slate-500' }}">
                                                {{ $line['sku'] }} · {{ rtrim(rtrim($line['available'], '0'), '.') ?: '0' }} {{ $line['unit'] }} in stock
                                            </div>
                                        </td>
                                        <td class="px-3 py-2"><input type="number" step="0.001" min="0" wire:model.live.debounce.300ms="lines.{{ $i }}.quantity" aria-label="Quantity" class="input w-24">
                                            @error("lines.$i.quantity")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                                        <td class="px-3 py-2"><input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="lines.{{ $i }}.unit_price" aria-label="Unit price" class="input w-28">
                                            @error("lines.$i.unit_price")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                                        <td class="px-3 py-2 text-right">{{ is_numeric($line['quantity']) && is_numeric($line['unit_price']) ? number_format((float) \App\Support\Money::mul($line['quantity'], \App\Support\Money::round($line['unit_price'])), 2) : '—' }}</td>
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
                <x-ui.field label="Customer" name="customerId">
                    <select id="customerId" wire:model="customerId" class="input">
                        <option value="">Walk-in customer</option>
                        @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Sale date" name="saleDate"><input id="saleDate" type="date" wire:model="saleDate" class="input"></x-ui.field>
                <x-ui.field label="Due date" name="dueDate" hint="Leave blank to use the company's default payment terms."><input id="dueDate" type="date" wire:model="dueDate" class="input"></x-ui.field>
                <x-ui.field label="Notes" name="notes"><input id="notes" type="text" wire:model="notes" class="input" maxlength="2000"></x-ui.field>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 font-semibold">Totals</h2>
                <div class="mb-4 grid grid-cols-2 gap-3">
                    <x-ui.field label="Discount" name="discountValue"><input id="discountValue" type="number" step="0.01" min="0" wire:model.live.debounce.300ms="discountValue" class="input"></x-ui.field>
                    <x-ui.field label="Type" name="discountType">
                        <select id="discountType" wire:model.live="discountType" class="input"><option value="fixed">Amount ({{ $currency }})</option><option value="percent">Percent (%)</option></select>
                    </x-ui.field>
                    <x-ui.field label="Tax rate (%)" name="taxRate" class="col-span-2"><input id="taxRate" type="number" step="0.01" min="0" max="100" wire:model.live.debounce.300ms="taxRate" class="input"></x-ui.field>
                </div>
                <dl class="space-y-1 border-t border-slate-100 pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd>{{ number_format((float) $totals['subtotal'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Discount</dt><dd>− {{ number_format((float) $totals['discount'], 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Tax</dt><dd>{{ number_format((float) $totals['tax'], 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total ({{ $currency }})</dt><dd>{{ number_format((float) $totals['total'], 2) }}</dd></div>
                </dl>
            </section>

            @can('payments.record')
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    @php
                        $paying = $takePayment;
                        $stkOn = $paying && $payMethod === 'mpesa' && $mpesaReady;
                        $tile = 'flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 text-center text-xs font-medium transition-colors';
                    @endphp
                    <h2 class="mb-1 font-semibold">How is the customer paying?</h2>
                    <p class="mb-3 text-xs text-slate-500">Choose one. Leave it on Pay later to invoice now. A smaller amount leaves the invoice partly paid.</p>
                    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Payment method">
                        <button type="button" wire:click="payLater" aria-pressed="{{ $paying ? 'false' : 'true' }}" class="{{ $tile }} {{ $paying ? 'border-slate-200 text-slate-600 hover:bg-slate-50' : 'border-indigo-600 bg-indigo-50 text-indigo-700' }}">
                            <x-sales.pay-icon method="later" /> Pay later
                        </button>
                        @foreach ($paymentMethods as $slug => $label)
                            @php($on = $paying && $payMethod === $slug)
                            <button type="button" wire:click="selectPay('{{ $slug }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" class="{{ $tile }} {{ $on ? ($slug === 'mpesa' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-indigo-600 bg-indigo-50 text-indigo-700') : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                                <x-sales.pay-icon :method="$slug" /> {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    @if ($paying)
                        <div class="mt-4 space-y-3">
                            <x-ui.field label="Amount ({{ $currency }}){{ $stkOn ? ', whole shillings' : '' }}" name="payNow"><input id="payNow" type="number" step="{{ $stkOn ? '1' : '0.01' }}" min="0" wire:model="payNow" class="input"></x-ui.field>
                            @if ($stkOn)
                                <x-ui.field label="Customer phone (Safaricom)" name="mpesaPhone" hint="A prompt is sent to this phone when you complete the sale. The payment is recorded automatically once they enter their PIN."><input id="mpesaPhone" type="tel" inputmode="tel" wire:model="mpesaPhone" class="input" placeholder="0712 345 678" autocomplete="off"></x-ui.field>
                            @else
                                <x-ui.field label="Reference" name="payReference" hint="{{ $payMethod === 'mpesa' ? 'M-Pesa prompts are not set up yet, so enter the receipt number of the payment.' : '' }}"><input id="payReference" type="text" wire:model="payReference" class="input" maxlength="255"></x-ui.field>
                            @endif
                        </div>
                    @endif
                </section>
            @endcan

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Complete sale</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </aside>
    </form>
</div>
