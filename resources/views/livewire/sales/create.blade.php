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
        @php
            $mpStep = ! $mpesaTx ? 1 : ($mpesaTx->status === 'pending' ? 2 : 3);
            $mpFailed = $mpesaTx && in_array($mpesaTx->status, ['failed', 'needs_review'], true);
            $mpAmount = (float) ($mpesaTx->amount ?? ($mpesaAmount !== '' ? $mpesaAmount : 0));
            $mpTitle = match (true) {
                ! $mpesaTx => 'Collect payment',
                $mpesaTx->status === 'pending' => 'Waiting for customer',
                $mpesaTx->status === 'success' => 'Payment received',
                $mpesaTx->status === 'needs_review' => 'Needs review',
                default => 'Payment not completed',
            };
        @endphp
        <div class="fixed inset-0 z-40 flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="M-Pesa payment" wire:keydown.escape.window="closeMpesa">
            <div class="absolute inset-0 animate-fade-in bg-emerald-950/50 backdrop-blur-sm" wire:click="closeMpesa"></div>

            <div class="relative max-h-[94vh] w-full max-w-md animate-pop overflow-y-auto rounded-t-3xl bg-white shadow-2xl ring-1 ring-black/5 sm:rounded-3xl">
                {{-- Green header: brand, amount, close --}}
                <div class="relative overflow-hidden bg-gradient-to-br from-emerald-500 via-emerald-600 to-green-700 px-6 pb-12 pt-5 text-white">
                    <span class="pointer-events-none absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10"></span>
                    <span class="pointer-events-none absolute -bottom-16 -left-8 h-36 w-36 rounded-full bg-white/10"></span>
                    <span class="pointer-events-none absolute right-16 top-16 h-6 w-6 rounded-full bg-white/10"></span>

                    <div class="relative flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-sm">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5h3A2.25 2.25 0 0115.75 3.75v16.5A2.25 2.25 0 0113.5 22.5h-3a2.25 2.25 0 01-2.25-2.25V3.75A2.25 2.25 0 0110.5 1.5zM11 19.5h2"/></svg>
                            </span>
                            <div class="leading-tight">
                                <p class="text-sm font-bold tracking-wide">M-PESA</p>
                                <p class="text-[11px] text-emerald-100">Lipa na M-Pesa</p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeMpesa" class="rounded-full bg-white/15 p-2 text-white transition hover:bg-white/25" aria-label="Close">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="relative mt-6">
                        <p class="text-xs font-medium uppercase tracking-widest text-emerald-100">{{ $mpTitle }}</p>
                        <p class="mt-1 flex items-baseline gap-2"><span class="text-lg font-medium text-emerald-100">{{ $currency }}</span><span class="text-4xl font-bold tracking-tight">{{ number_format($mpAmount, 2) }}</span></p>
                        <p class="mt-1 truncate text-sm text-emerald-50">{{ $customerName }}</p>
                    </div>

                    {{-- Curved edge into the white body --}}
                    <svg class="absolute -bottom-px left-0 w-full text-white" viewBox="0 0 400 24" preserveAspectRatio="none" aria-hidden="true"><path d="M0 24V8C60 22 140 0 200 6s140 16 200 2v14z" fill="currentColor"/></svg>
                </div>

                {{-- Steps --}}
                <div class="mt-2 flex items-center justify-center gap-2 px-6 pb-1 text-[11px] font-medium" aria-hidden="true">
                    @foreach (['Details', 'Customer pays', 'Done'] as $i => $label)
                        @php($n = $i + 1)
                        <span class="flex items-center gap-1.5 {{ $n <= $mpStep ? ($mpFailed && $n === 3 ? 'text-red-600' : 'text-emerald-700') : 'text-slate-400' }}">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] {{ $n < $mpStep || ($n === 3 && $mpStep === 3 && ! $mpFailed) ? 'bg-emerald-600 text-white' : ($n === $mpStep ? ($mpFailed ? 'bg-red-500 text-white' : 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-500') : 'bg-slate-100 text-slate-400') }}">{{ $n < $mpStep || ($n === 3 && $mpStep === 3 && ! $mpFailed) ? '✓' : $n }}</span>{{ $label }}
                        </span>
                        @unless ($loop->last)<span class="h-px w-4 {{ $n < $mpStep ? 'bg-emerald-500' : 'bg-slate-200' }}"></span>@endunless
                    @endforeach
                </div>

                <div class="px-6 pb-6 pt-4">
                    @if (! $mpesaTx)
                        <form wire:submit="sendMpesa" class="space-y-4">
                            @unless ($mpesaReady)
                                <div class="rounded-xl bg-amber-50 px-3.5 py-2.5 text-sm text-amber-800 ring-1 ring-amber-200" role="alert">
                                    M-Pesa prompts are not connected yet. An administrator adds the Daraja details on the Company page.
                                    <button type="button" wire:click="useManualMpesa" class="font-semibold underline">I already have the M-Pesa code</button>
                                </div>
                            @endunless

                            <x-ui.field label="Customer phone number" name="mpesaPhone">
                                <div class="flex overflow-hidden rounded-xl ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-emerald-500">
                                    <span class="inline-flex items-center bg-slate-50 px-3 text-sm font-medium text-slate-600">+254</span>
                                    <input id="mpesaPhone" type="tel" inputmode="tel" wire:model="mpesaPhone" class="w-full border-0 bg-white px-3 py-3 text-base outline-none focus:ring-0" placeholder="712 345 678" autofocus autocomplete="off">
                                </div>
                            </x-ui.field>

                            <x-ui.field label="Amount to charge" name="mpesaAmount">
                                <div class="flex overflow-hidden rounded-xl ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-emerald-500">
                                    <span class="inline-flex items-center bg-slate-50 px-3 text-sm font-medium text-slate-600">{{ $currency }}</span>
                                    <input id="mpesaAmount" type="number" step="1" min="1" wire:model.live.debounce.300ms="mpesaAmount" class="w-full border-0 bg-white px-3 py-3 text-base font-semibold outline-none focus:ring-0">
                                </div>
                                <p class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                                    <span>Whole shillings. Enter less for part payment.</span>
                                    @if ((string) $mpesaAmount !== (string) floor((float) $totals['total']))
                                        <button type="button" wire:click="$set('mpesaAmount', '{{ floor((float) $totals['total']) }}')" class="rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 hover:bg-emerald-100">Full {{ number_format(floor((float) $totals['total'])) }}</button>
                                    @endif
                                </p>
                            </x-ui.field>

                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 px-5 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-600/25 transition hover:from-emerald-700 hover:to-green-700 disabled:opacity-60" wire:loading.attr="disabled" wire:target="sendMpesa">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.3 3.9a.75.75 0 011-.9l17 8.5a.75.75 0 010 1.4l-17 8.5a.75.75 0 01-1-.9L6 12zm0 0h7.5"/></svg>
                                <span wire:loading.remove wire:target="sendMpesa">Send payment prompt</span><span wire:loading wire:target="sendMpesa">Sending&hellip;</span>
                            </button>
                            <p class="flex items-center justify-center gap-1.5 text-center text-xs text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 10-9 0v3m-1.5 0h12a1.5 1.5 0 011.5 1.5v7.5a1.5 1.5 0 01-1.5 1.5H6A1.5 1.5 0 014.5 19.5V12A1.5 1.5 0 016 10.5z"/></svg>
                                Safaricom sends the customer a PIN prompt. Nothing is charged until they enter it.
                            </p>
                        </form>

                    @elseif ($mpesaTx->status === 'pending')
                        <div wire:poll.3s="checkMpesa" class="space-y-5 text-center" aria-live="polite">
                            <div class="relative mx-auto flex h-36 w-36 items-center justify-center">
                                <span class="mp-ring2 absolute inset-0 rounded-full bg-emerald-100"></span>
                                <span class="mp-ring2 absolute inset-0 rounded-full bg-emerald-100" style="animation-delay: 1s"></span>
                                <span class="absolute inset-3 rounded-full bg-gradient-to-br from-emerald-50 to-emerald-100"></span>
                                <div class="relative h-24 w-32 overflow-hidden">
                                    <svg class="absolute inset-0 h-full w-full" viewBox="0 0 176 112" fill="none" aria-hidden="true">
                                        <g class="mp-fly">
                                            <path d="M54 44 28 52M48 62 22 72M60 78 40 92" stroke="#16a34a" stroke-width="3.5" stroke-linecap="round" class="mp-streak"/>
                                            <path d="M130 20 66 42l18 13z" fill="#16a34a"/>
                                            <path d="M130 20 84 55l-5 26z" fill="#15803d"/>
                                            <path d="M84 55l-5 26 21-19z" fill="#14532d"/>
                                        </g>
                                    </svg>
                                </div>
                            </div>

                            <div>
                                <p class="text-xl font-bold text-slate-900">Waiting for PIN<span class="mp-dot">.</span><span class="mp-dot" style="animation-delay:.2s">.</span><span class="mp-dot" style="animation-delay:.4s">.</span></p>
                                <p class="mt-1 text-sm text-slate-500">A prompt is on <strong class="text-slate-700">{{ $mpesaTx->phone }}</strong>. Ask {{ $customerName === 'Walk-in customer' ? 'the customer' : $customerName }} to enter their M-Pesa PIN.</p>
                            </div>

                            <ol class="mx-auto max-w-xs space-y-2 text-left text-sm">
                                <li class="flex items-center gap-3 text-emerald-700"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs text-white">✓</span>Prompt sent</li>
                                <li class="flex items-center gap-3 font-medium text-slate-800"><span class="mp-pulse flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 ring-2 ring-emerald-500"><span class="h-2 w-2 rounded-full bg-emerald-600"></span></span>Customer enters PIN</li>
                                <li class="flex items-center gap-3 text-slate-400"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs">3</span>Payment confirmed</li>
                            </ol>

                            <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-500">The sale is already saved. If you close this, the payment is still recorded when it arrives.</p>
                            <button type="button" wire:click="closeMpesa" class="w-full rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-200">Close</button>
                        </div>

                    @elseif ($mpesaTx->status === 'success')
                        <div class="space-y-4 text-center">
                            <svg class="mx-auto h-24 w-24 overflow-visible" viewBox="0 0 100 100" aria-hidden="true">
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

                            <div class="mp-rise" style="animation-delay:.35s">
                                <p class="text-xl font-bold text-slate-900">Payment successful</p>
                                <p class="text-sm text-slate-500">Recorded on the sale automatically.</p>
                            </div>

                            {{-- Receipt --}}
                            <div class="mp-rise overflow-hidden rounded-2xl bg-slate-50 text-left ring-1 ring-slate-200" style="animation-delay:.5s">
                                <div class="px-5 py-4 text-center">
                                    <p class="text-[11px] font-medium uppercase tracking-widest text-slate-400">M-Pesa code</p>
                                    <div class="mt-1 flex items-center justify-center gap-2" x-data="{ copied: false }">
                                        <span class="font-mono text-2xl font-bold tracking-widest text-emerald-700">{{ $mpesaTx->mpesa_receipt ?: '—' }}</span>
                                        @if ($mpesaTx->mpesa_receipt)
                                            <button type="button" class="rounded-lg bg-white p-1.5 text-slate-500 ring-1 ring-slate-200 hover:text-emerald-700" x-on:click="navigator.clipboard?.writeText('{{ $mpesaTx->mpesa_receipt }}'); copied = true; setTimeout(() => copied = false, 1500)" aria-label="Copy code">
                                                <svg x-show="! copied" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                <svg x-show="copied" x-cloak class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="relative border-t border-dashed border-slate-300">
                                    <span class="absolute -left-2.5 -top-2.5 h-5 w-5 rounded-full bg-white ring-1 ring-slate-200"></span>
                                    <span class="absolute -right-2.5 -top-2.5 h-5 w-5 rounded-full bg-white ring-1 ring-slate-200"></span>
                                </div>
                                <dl class="space-y-2.5 px-5 py-4 text-sm">
                                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Name</dt><dd class="font-medium text-slate-800">{{ $customerName }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Amount</dt><dd class="font-bold text-emerald-700">{{ $currency }} {{ number_format((float) $mpesaTx->amount, 2) }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Phone</dt><dd class="text-slate-800">{{ $mpesaTx->phone }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Date and time</dt><dd class="text-slate-800">{{ ($mpesaTx->completed_at ?? $mpesaTx->updated_at)->format('d M Y, H:i') }}</dd></div>
                                </dl>
                            </div>

                            <button type="button" wire:click="goToSale" class="mp-rise w-full rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 px-5 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-600/25 hover:from-emerald-700 hover:to-green-700" style="animation-delay:.9s">View sale</button>
                        </div>

                    @elseif ($mpesaTx->status === 'needs_review')
                        <div class="space-y-4 text-center">
                            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-3xl text-amber-600" aria-hidden="true">!</span>
                            <p class="text-xl font-bold text-slate-900">Payment needs review</p>
                            <p class="text-sm text-slate-600">The customer paid{{ $mpesaTx->mpesa_receipt ? ' (code '.$mpesaTx->mpesa_receipt.')' : '' }} but it could not be added to the sale: {{ $mpesaTx->result_desc }} Please check with an administrator.</p>
                            <button type="button" wire:click="goToSale" class="w-full rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-200">View sale</button>
                        </div>

                    @else
                        <div class="space-y-4 text-center">
                            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-500 ring-8 ring-red-50/60" aria-hidden="true">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                            </span>
                            <div>
                                <p class="text-xl font-bold text-slate-900">Payment not completed</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $mpesaTx->result_desc ?: 'The customer cancelled or the prompt timed out.' }}</p>
                                <p class="mt-1 text-xs text-slate-400">Nothing was charged. The sale is saved and unpaid.</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" wire:click="goToSale" class="rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-200">View sale</button>
                                <button type="button" wire:click="retryMpesa" class="rounded-xl bg-gradient-to-r from-emerald-600 to-green-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 hover:from-emerald-700 hover:to-green-700">Try again</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
