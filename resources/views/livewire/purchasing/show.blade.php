<div>
    <a href="{{ route('purchase-orders.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Purchase orders</a>

    @if ($notice)<p class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
    @error('action')<p class="mt-3 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

    <div class="mb-6 mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-3 text-2xl font-semibold">{{ $order->po_number }} <x-purchasing.status :order="$order" /></h1>
            <p class="text-sm text-slate-500">
                {{ $order->order_date->format('d M Y') }} ·
                @can('suppliers.view')<a href="{{ route('suppliers.show', $order->supplier_id) }}" class="text-indigo-600 hover:underline">{{ $order->supplier?->name }}</a>@else{{ $order->supplier?->name }}@endcan
                · Created by {{ $order->creator?->name ?? 'unknown' }}
                @if ($order->expected_date) · Expected {{ $order->expected_date->format('d M Y') }}@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($order->status === 'draft')
                @can('purchase_orders.manage')
                    <a href="{{ route('purchase-orders.edit', $order) }}" class="btn-secondary">Edit</a>
                    <button type="button" wire:click="submit" class="btn-primary">Submit for approval</button>
                @endcan
            @endif
            @if ($order->status === 'pending_approval')
                @can('purchase_orders.approve')
                    <button type="button" wire:click="openReason('reject')" class="btn-secondary">Reject</button>
                    <button type="button" wire:click="approve" class="btn-primary">Approve</button>
                @endcan
            @endif
            @if ($order->canReceive())
                @can('goods.receive')<button type="button" wire:click="openReceive" class="btn-primary">Receive goods</button>@endcan
            @endif
            @if (in_array($order->status, ['draft', 'pending_approval', 'approved'], true))
                @can('purchase_orders.manage')<button type="button" wire:click="openReason('cancel')" class="btn-danger">Cancel order</button>@endcan
            @endif
        </div>
    </div>

    @if ($order->status === 'draft' && $order->rejection_reason)
        <p class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Returned for changes: {{ $order->rejection_reason }}</p>
    @endif
    @if ($order->status === 'cancelled')
        <p class="mb-6 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700">Cancelled on {{ $order->cancelled_at->format('d M Y H:i') }}: {{ $order->cancel_reason }}.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Item</th><th class="px-4 py-3 text-right">Ordered</th><th class="px-4 py-3 text-right">Received</th><th class="px-4 py-3 text-right">Unit cost</th><th class="px-4 py-3 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <tr wire:key="item-{{ $item->id }}">
                                <td class="px-4 py-3"><div class="font-medium">{{ $item->product_name }}</div><div class="text-xs text-slate-500">{{ $item->sku }}</div></td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim($item->quantity_ordered, '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim($item->quantity_received, '0'), '.') ?: '0' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->unit_cost, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-3 font-semibold">Goods receipts</h2>
                @forelse ($order->receipts as $receipt)
                    <div wire:key="grn-{{ $receipt->id }}" class="border-t border-slate-100 py-3 text-sm first:border-t-0">
                        <div class="font-medium">{{ $receipt->receipt_number }} <span class="font-normal text-slate-500">· {{ $receipt->received_date->format('d M Y') }} · {{ $receipt->user?->name ?? 'unknown' }}</span></div>
                        <ul class="mt-1 text-slate-600">
                            @foreach ($receipt->items as $line)
                                <li>{{ rtrim(rtrim($line->quantity, '0'), '.') }} × {{ $order->items->firstWhere('id', $line->purchase_order_item_id)?->product_name }}</li>
                            @endforeach
                        </ul>
                        @if ($receipt->notes)<p class="mt-1 text-xs text-slate-500">{{ $receipt->notes }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nothing received yet.</p>
                @endforelse
            </section>

            @if ($order->isPayable() || $order->payments->isNotEmpty())
                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold">Supplier payments</h2>
                        @if ($order->isPayable() && bccomp($order->balance(), '0', 2) > 0)
                            @can('supplier_payments.record')<button type="button" wire:click="openPayment" class="btn-primary">Record payment</button>@endcan
                        @endif
                    </div>
                    @forelse ($order->payments as $payment)
                        <div wire:key="pay-{{ $payment->id }}" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-3 text-sm first:border-t-0">
                            <div>
                                <span class="font-medium {{ $payment->isVoided() ? 'text-slate-400 line-through' : '' }}">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</span>
                                <span class="text-slate-500">· {{ $paymentMethods[$payment->method] ?? $payment->method }} · {{ $payment->paid_at->format('d M Y') }}@if ($payment->reference) · {{ $payment->reference }}@endif</span>
                                @if ($payment->isVoided())<span class="badge ml-1 bg-slate-200 text-slate-700">Voided: {{ $payment->void_reason }}</span>@endif
                            </div>
                            @if (! $payment->isVoided())
                                @can('supplier_payments.record')<button type="button" wire:click="openVoid({{ $payment->id }})" class="text-red-600 hover:underline">Void</button>@endcan
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No payments recorded.</p>
                    @endforelse
                </section>
            @endif
        </div>

        <aside class="h-fit rounded-xl border border-slate-200 bg-white p-5">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd>{{ number_format((float) $order->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Tax ({{ rtrim(rtrim($order->tax_rate, '0'), '.') ?: '0' }}%)</dt><dd>{{ number_format((float) $order->tax_amount, 2) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total ({{ $currency }})</dt><dd>{{ number_format((float) $order->total, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Paid</dt><dd>{{ number_format((float) $order->amount_paid, 2) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>Balance owed</dt><dd>{{ number_format((float) $order->balance(), 2) }}</dd></div>
                @if ($order->approver)<div class="flex justify-between pt-2"><dt class="text-slate-600">Approved by</dt><dd>{{ $order->approver->name }}</dd></div>@endif
            </dl>
            @if ($order->notes)<p class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $order->notes }}</p>@endif
        </aside>
    </div>

    @if ($reasonModal)
        <x-ui.modal :title="$reasonModal === 'reject' ? 'Reject purchase order' : 'Cancel purchase order'" close="closeModals">
            <form wire:submit="submitReason" class="space-y-4">
                <p class="text-sm text-slate-600">{{ $reasonModal === 'reject' ? 'The order goes back to draft so the buyer can change it.' : 'Cancelled orders stay on record. This is not possible once goods are received or payments exist.' }}</p>
                <x-ui.field label="Reason (required)" name="reason"><input id="reason" type="text" wire:model="reason" class="input" maxlength="255" autofocus></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Back</button><button type="submit" class="btn-danger">{{ $reasonModal === 'reject' ? 'Reject' : 'Cancel order' }}</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showReceive)
        <x-ui.modal title="Receive goods" close="closeModals" maxWidth="max-w-2xl">
            <form wire:submit="receive" class="space-y-4">
                @error('receiveQty')<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="py-2">Item</th><th class="px-2 py-2 text-right">Outstanding</th><th class="py-2 pl-2">Receive now</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            @if (bccomp($item->quantityOutstanding(), '0', 3) > 0)
                                <tr wire:key="rcv-{{ $item->id }}">
                                    <td class="py-2"><div class="font-medium">{{ $item->product_name }}</div><div class="text-xs text-slate-500">{{ $item->sku }}</div></td>
                                    <td class="px-2 py-2 text-right">{{ rtrim(rtrim($item->quantityOutstanding(), '0'), '.') }}</td>
                                    <td class="py-2 pl-2"><input type="number" step="0.001" min="0" max="{{ $item->quantityOutstanding() }}" wire:model="receiveQty.{{ $item->id }}" aria-label="Quantity received for {{ $item->product_name }}" class="input w-28">
                                        @error("receiveQty.{$item->id}")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <x-ui.field label="Date received" name="receiveDate"><input id="receiveDate" type="date" wire:model="receiveDate" max="{{ today()->toDateString() }}" class="input"></x-ui.field>
                <x-ui.field label="Notes" name="receiveNotes"><input id="receiveNotes" type="text" wire:model="receiveNotes" class="input" maxlength="2000"></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary" wire:loading.attr="disabled">Confirm receipt</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showPayment)
        <x-ui.modal title="Record supplier payment" close="closeModals">
            <form wire:submit="recordPayment" class="space-y-4">
                <p class="text-sm text-slate-600">Balance owed: <strong>{{ $currency }} {{ number_format((float) $order->balance(), 2) }}</strong></p>
                <x-ui.field label="Amount" name="payAmount"><input id="payAmount" type="number" step="0.01" min="0" wire:model="payAmount" class="input" autofocus></x-ui.field>
                <x-ui.field label="Method" name="payMethod"><select id="payMethod" wire:model="payMethod" class="input">@foreach ($paymentMethods as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Date paid" name="payDate"><input id="payDate" type="date" wire:model="payDate" max="{{ today()->toDateString() }}" class="input"></x-ui.field>
                <x-ui.field label="Reference" name="payReference"><input id="payReference" type="text" wire:model="payReference" class="input" maxlength="255"></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary" wire:loading.attr="disabled">Record payment</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($voidPayment)
        <x-ui.modal title="Void payment" close="closeModals">
            <form wire:submit="voidPayment" class="space-y-4">
                <p class="text-sm text-slate-600">This reverses the payment of {{ $currency }} {{ number_format((float) $voidPayment->amount, 2) }}. The record is kept for the audit trail.</p>
                <x-ui.field label="Reason (required)" name="voidReason"><input id="voidReason" type="text" wire:model="voidReason" class="input" maxlength="255" autofocus></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Keep payment</button><button type="submit" class="btn-danger">Void payment</button></div>
            </form>
        </x-ui.modal>
    @endif
</div>
