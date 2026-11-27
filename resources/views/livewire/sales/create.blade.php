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
                    <h2 class="mb-1 font-semibold">Payment</h2>
                    <p class="mb-3 text-xs text-slate-500">Choose how the customer is paying. Leave it empty to invoice now and collect later.</p>
                    <x-ui.field label="Payment method" name="payMethod">
                        <select id="payMethod" wire:model.live="payMethod" class="input">
                            <option value="">Select a payment method</option>
                            @foreach ($payMethods as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                        </select>
                    </x-ui.field>

                    @if ($payMethod === 'cash')
                        <div class="mt-4 space-y-3">
                            <x-ui.field label="Amount received ({{ $currency }})" name="payNow"><input id="payNow" type="number" step="0.01" min="0" wire:model="payNow" class="input"></x-ui.field>
                            <p class="text-xs text-slate-500">Recorded as cash received from <strong>{{ $customerName }}</strong>.</p>
                        </div>
                    @elseif ($payMethod === 'bank_transfer' || ($payMethod === 'mpesa' && ! $mpesaReady))
                        <div class="mt-4 space-y-3">
                            <x-ui.field label="Amount received ({{ $currency }})" name="payNow"><input id="payNow" type="number" step="0.01" min="0" wire:model="payNow" class="input"></x-ui.field>
                            <x-ui.field label="{{ $payMethod === 'mpesa' ? 'M-Pesa code' : 'Transaction code' }}" name="payReference" hint="{{ $payMethod === 'mpesa' ? 'Prompts are not set up yet, so enter the code from the M-Pesa message.' : '' }}"><input id="payReference" type="text" wire:model="payReference" class="input" maxlength="255" autocomplete="off"></x-ui.field>
                            @if ($payMethod === 'bank_transfer')
                                <x-ui.field label="Paid by (name)" name="payerName" hint="Leave blank to use {{ $customerName }}."><input id="payerName" type="text" wire:model="payerName" class="input" maxlength="255" placeholder="{{ $customerName }}"></x-ui.field>
                                <x-ui.field label="Bank" name="payBank"><input id="payBank" type="text" wire:model="payBank" class="input" maxlength="255" placeholder="e.g. Equity Bank"></x-ui.field>
                            @endif
                            <x-ui.field label="Date and time of payment" name="payTime"><input id="payTime" type="datetime-local" wire:model="payTime" class="input"></x-ui.field>
                        </div>
                    @endif
                </section>
            @endcan

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Complete sale</span><span wire:loading wire:target="save">Saving…</span>
            </button>
        </aside>
    </form>

    @if ($showMpesa)
        <x-ui.modal title="Pay with M-Pesa" close="closeMpesa">
            @if (! $mpesaTx)
                <form wire:submit="sendMpesa" class="space-y-4">
                    <p class="text-sm text-slate-600">Paying: <strong>{{ $customerName }}</strong>. A prompt goes to this phone and the customer enters their M-Pesa PIN.</p>
                    <x-ui.field label="Recipient phone (Safaricom)" name="mpesaPhone"><input id="mpesaPhone" type="tel" inputmode="tel" wire:model="mpesaPhone" class="input" placeholder="0712 345 678" autofocus autocomplete="off"></x-ui.field>
                    <x-ui.field label="Amount ({{ $currency }}, whole shillings)" name="mpesaAmount"><input id="mpesaAmount" type="number" step="1" min="1" wire:model="mpesaAmount" class="input"></x-ui.field>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeMpesa" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="sendMpesa">
                            <span wire:loading.remove wire:target="sendMpesa">Send payment prompt</span><span wire:loading wire:target="sendMpesa">Sending&hellip;</span>
                        </button>
                    </div>
                </form>
            @elseif ($mpesaTx->status === 'pending')
                <div wire:poll.3s="checkMpesa" class="space-y-4 py-4 text-center" aria-live="polite">
                    <svg class="mx-auto h-12 w-12 animate-spin text-emerald-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".2" stroke-width="3"/><path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    <p class="font-semibold text-slate-800">Waiting for payment</p>
                    <p class="text-sm text-slate-600">A prompt was sent to {{ $mpesaTx->phone }}. Ask the customer to enter their M-Pesa PIN. This updates by itself.</p>
                    <p class="text-xs text-slate-400">The sale is already saved. You can close this and the payment will still be recorded when it arrives.</p>
                    <button type="button" wire:click="closeMpesa" class="btn-secondary">Close</button>
                </div>
            @elseif ($mpesaTx->status === 'success')
                <div class="space-y-4 text-center">
                    <svg class="mx-auto h-14 w-14 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 12.5l2.7 2.7L16 9.8"/></svg>
                    <p class="text-lg font-semibold text-emerald-700">Payment successful</p>
                    <dl class="divide-y divide-slate-100 rounded-lg border border-slate-200 text-left text-sm">
                        <div class="flex justify-between gap-4 px-4 py-2"><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $customerName }}</dd></div>
                        <div class="flex justify-between gap-4 px-4 py-2"><dt class="text-slate-500">M-Pesa code</dt><dd class="font-mono font-medium">{{ $mpesaTx->mpesa_receipt ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-4 px-4 py-2"><dt class="text-slate-500">Amount</dt><dd class="font-medium">{{ $currency }} {{ number_format((float) $mpesaTx->amount, 2) }}</dd></div>
                        <div class="flex justify-between gap-4 px-4 py-2"><dt class="text-slate-500">Phone</dt><dd>{{ $mpesaTx->phone }}</dd></div>
                        <div class="flex justify-between gap-4 px-4 py-2"><dt class="text-slate-500">Time</dt><dd>{{ ($mpesaTx->completed_at ?? $mpesaTx->updated_at)->format('d M Y, H:i') }}</dd></div>
                    </dl>
                    <p class="text-xs text-slate-500">The payment has been recorded on the sale.</p>
                    <button type="button" wire:click="goToSale" class="btn-primary">View sale</button>
                </div>
            @elseif ($mpesaTx->status === 'needs_review')
                <div class="space-y-3 text-center">
                    <p class="text-lg font-semibold text-amber-700">Payment needs review</p>
                    <p class="text-sm text-slate-600">The customer paid{{ $mpesaTx->mpesa_receipt ? ' (code '.$mpesaTx->mpesa_receipt.')' : '' }} but it could not be added to the sale: {{ $mpesaTx->result_desc }} Please check with an administrator.</p>
                    <button type="button" wire:click="goToSale" class="btn-secondary">View sale</button>
                </div>
            @else
                <div class="space-y-3 text-center">
                    <p class="text-lg font-semibold text-red-700">Payment was not completed</p>
                    <p class="text-sm text-slate-600">{{ $mpesaTx->result_desc ?: 'The customer cancelled or the prompt timed out.' }} Nothing was charged. The sale is saved and unpaid.</p>
                    <div class="flex justify-center gap-2"><button type="button" wire:click="goToSale" class="btn-secondary">View sale</button><button type="button" wire:click="retryMpesa" class="btn-primary">Try again</button></div>
                </div>
            @endif
        </x-ui.modal>
    @endif
</div>
