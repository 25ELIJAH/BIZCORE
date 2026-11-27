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
                    @elseif ($payMethod === 'bank_transfer' || ($payMethod === 'mpesa' && $mpesaManual))
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
        <x-ui.modal title="M-Pesa payment" close="closeMpesa" maxWidth="max-w-md">
            @if (! $mpesaTx)
            {{-- Amount banner, shown in every step --}}
            <div class="-mx-5 -mt-4 mb-5 flex items-center gap-3 bg-emerald-600 px-5 py-4 text-white">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5h3A2.25 2.25 0 0115.75 3.75v16.5A2.25 2.25 0 0113.5 22.5h-3a2.25 2.25 0 01-2.25-2.25V3.75A2.25 2.25 0 0110.5 1.5zM11 19.5h2"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-wide text-emerald-100">Amount to collect</p>
                    <p class="truncate text-2xl font-semibold">{{ $currency }} {{ number_format((float) ($mpesaTx->amount ?? ($mpesaAmount !== '' ? $mpesaAmount : 0)), 2) }}</p>
                </div>
            </div>
            @endif

            @if (! $mpesaTx)
                <form wire:submit="sendMpesa" class="space-y-4">
                    @unless ($mpesaReady)
                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                            M-Pesa prompts are not connected yet. An administrator adds the Daraja details on the Company page.
                            <button type="button" wire:click="useManualMpesa" class="font-semibold underline">I already have the M-Pesa code</button>
                        </div>
                    @endunless
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-500">Customer</span><span class="font-medium">{{ $customerName }}</span>
                    </div>
                    <x-ui.field label="Recipient phone number" name="mpesaPhone" hint="The Safaricom number that will get the prompt.">
                        <div class="flex">
                            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">+254</span>
                            <input id="mpesaPhone" type="tel" inputmode="tel" wire:model="mpesaPhone" class="input rounded-l-none" placeholder="712 345 678" autofocus autocomplete="off">
                        </div>
                    </x-ui.field>
                    <x-ui.field label="Amount ({{ $currency }})" name="mpesaAmount" hint="Whole shillings only. Enter less to take part payment.">
                        <input id="mpesaAmount" type="number" step="1" min="1" wire:model.live.debounce.300ms="mpesaAmount" class="input">
                    </x-ui.field>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="closeMpesa" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="sendMpesa">
                            <span wire:loading.remove wire:target="sendMpesa">Send payment prompt</span><span wire:loading wire:target="sendMpesa">Sending&hellip;</span>
                        </button>
                    </div>
                </form>
            @elseif ($mpesaTx->status === 'pending')
                <div wire:poll.3s="checkMpesa" class="space-y-3 px-2 pb-2 pt-4 text-center" aria-live="polite">
                    <div class="relative mx-auto h-28 w-44 overflow-hidden">
                        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 176 112" fill="none" aria-hidden="true">
                            <g class="mp-fly">
                                <path d="M54 44 28 52M48 62 22 72M60 78 40 92" stroke="#16a34a" stroke-width="3.5" stroke-linecap="round" class="mp-streak"/>
                                <path d="M130 20 66 42l18 13z" fill="#16a34a"/>
                                <path d="M130 20 84 55l-5 26z" fill="#15803d"/>
                                <path d="M84 55l-5 26 21-19z" fill="#14532d"/>
                            </g>
                        </svg>
                    </div>
                    <p class="text-xl font-semibold text-slate-900">Processing&hellip;</p>
                    <p class="text-sm text-slate-500">Your M-Pesa prompt is on <strong class="text-slate-700">{{ $mpesaTx->phone }}</strong>.<br>Ask {{ $customerName === 'Walk-in customer' ? 'the customer' : $customerName }} to enter their PIN.</p>
                    <div class="mx-auto flex max-w-xs items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
                        <span class="text-slate-500">Amount</span><span class="font-semibold">{{ $currency }} {{ number_format((float) $mpesaTx->amount, 2) }}</span>
                    </div>
                    <p class="text-xs text-slate-400">The sale is already saved. If you close this, the payment is still recorded when it arrives.</p>
                    <button type="button" wire:click="closeMpesa" class="rounded-full bg-slate-100 px-6 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Close</button>
                </div>
            @elseif ($mpesaTx->status === 'success')
                <div class="space-y-3 px-2 pb-2 pt-4 text-center">
                    <svg class="mx-auto h-28 w-28 overflow-visible" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" r="38" fill="none" stroke="#16a34a" stroke-width="2" class="mp-ring"/>
                        <circle cx="50" cy="50" r="38" fill="none" stroke="#16a34a" stroke-width="2" class="mp-ring" style="animation-delay:.25s"/>
                        <g class="mp-spark">
                            @foreach ([[0, -1], [0.87, -0.5], [0.87, 0.5], [0, 1], [-0.87, 0.5], [-0.87, -0.5]] as $i => [$dx, $dy])
                                <circle cx="50" cy="50" r="3" fill="{{ $i % 2 ? '#86efac' : '#16a34a' }}" style="--dx: {{ $dx * 54 }}px; --dy: {{ $dy * 54 }}px; animation-delay: {{ .3 + $i * .03 }}s"/>
                            @endforeach
                        </g>
                        <g class="mp-pop" style="transform-origin: 50px 50px">
                            <g fill="#16a34a">
                                <rect x="14" y="14" width="72" height="72" rx="22"/>
                                <rect x="14" y="14" width="72" height="72" rx="22" transform="rotate(30 50 50)"/>
                                <rect x="14" y="14" width="72" height="72" rx="22" transform="rotate(60 50 50)"/>
                            </g>
                            <path d="M33 52l11 11 23-25" pathLength="1" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" class="mp-draw"/>
                        </g>
                    </svg>
                    <p style="animation-delay: .35s" class="mp-rise text-xl font-semibold text-slate-900">Success!</p>
                    <p style="animation-delay: .45s" class="mp-rise text-sm text-slate-500">The payment was received and recorded.</p>
                    <dl class="divide-y divide-slate-100 rounded-xl bg-slate-50 text-left text-sm">
                        <div class="mp-rise flex justify-between gap-4 px-4 py-2.5" style="animation-delay: 0.6s"><dt class="text-slate-500">Name</dt><dd class="font-medium">{{ $customerName }}</dd></div>
                        <div class="mp-rise flex justify-between gap-4 px-4 py-2.5" style="animation-delay: 0.68s"><dt class="text-slate-500">M-Pesa code</dt><dd class="font-mono font-semibold tracking-wide">{{ $mpesaTx->mpesa_receipt ?: '—' }}</dd></div>
                        <div class="mp-rise flex justify-between gap-4 px-4 py-2.5" style="animation-delay: 0.76s"><dt class="text-slate-500">Amount</dt><dd class="font-semibold text-emerald-700">{{ $currency }} {{ number_format((float) $mpesaTx->amount, 2) }}</dd></div>
                        <div class="mp-rise flex justify-between gap-4 px-4 py-2.5" style="animation-delay: 0.84s"><dt class="text-slate-500">Phone</dt><dd>{{ $mpesaTx->phone }}</dd></div>
                        <div class="mp-rise flex justify-between gap-4 px-4 py-2.5" style="animation-delay: 0.92s"><dt class="text-slate-500">Time</dt><dd>{{ ($mpesaTx->completed_at ?? $mpesaTx->updated_at)->format('d M Y, H:i') }}</dd></div>
                    </dl>
                    <button type="button" wire:click="goToSale" style="animation-delay: 1.05s" class="mp-rise w-full rounded-full bg-slate-900 px-6 py-3 text-sm font-medium text-white hover:bg-slate-800">View sale</button>
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
