<div>
    <a href="{{ route('sales.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Sales</a>

    @if ($notice)<p class="mt-3 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">{{ $notice }}</p>@endif
    @error('email')<p class="mt-3 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

    <div class="mb-6 mt-2 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="flex items-center gap-3 text-2xl font-semibold">{{ $sale->invoice?->invoice_number }} <x-sales.status :sale="$sale" /></h1>
            <p class="text-sm text-slate-500">
                {{ $sale->sale_date->format('d M Y') }} ·
                @if ($sale->customer)
                    @can('customers.view')<a href="{{ route('customers.show', $sale->customer_id) }}" class="text-indigo-600 hover:underline">{{ $sale->customer->name }}</a>@else{{ $sale->customer->name }}@endcan
                @else Walk-in customer @endif
                · Recorded by {{ $sale->user?->name ?? 'unknown' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('sales.invoice', $sale) }}" target="_blank" class="btn-secondary">View invoice</a>
            <a href="{{ route('sales.invoice', [$sale, 'download' => 1]) }}" class="btn-secondary">Download PDF</a>
            @unless ($sale->isCancelled())
                @can('sales.edit')
                    <button type="button" wire:click="emailInvoice(false)" wire:loading.attr="disabled" wire:target="emailInvoice" class="btn-secondary">Email invoice</button>
                    @if ($sale->isOverdue())<button type="button" wire:click="emailInvoice(true)" wire:loading.attr="disabled" wire:target="emailInvoice" class="btn-primary">Send reminder</button>@endif
                @endcan
                @can('sales.edit')<button type="button" wire:click="openEdit" class="btn-secondary">Edit details</button>@endcan
                @can('sales.cancel')<button type="button" wire:click="openCancel" class="btn-danger">Cancel sale</button>@endcan
            @endunless
        </div>
    </div>

    @if ($sale->isCancelled())
        <p class="mb-6 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700">Cancelled on {{ $sale->cancelled_at->format('d M Y H:i') }}: {{ $sale->cancel_reason }}. Stock was restored.</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Item</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Unit price</th><th class="px-4 py-3 text-right">Total</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sale->items as $item)
                            <tr><td class="px-4 py-3"><div class="font-medium">{{ $item->product_name }}</div><div class="text-xs text-slate-500">{{ $item->sku }}</div></td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $item->line_total, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Payments</h2>
                    @if (! $sale->isCancelled() && bccomp($sale->balance(), '0', 2) > 0)
                        @can('payments.record')<button type="button" wire:click="openPayment" class="btn-primary">Record payment</button>@endcan
                    @endif
                </div>
                @forelse ($sale->payments as $payment)
                    <div wire:key="pay-{{ $payment->id }}" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-3 text-sm first:border-t-0">
                        <div>
                            <span class="font-medium {{ $payment->isVoided() ? 'text-slate-400 line-through' : '' }}">{{ $currency }} {{ number_format((float) $payment->amount, 2) }}</span>
                            <span class="text-slate-500">· {{ $paymentMethods[$payment->method] ?? $payment->method }} · {{ $payment->paid_at->format('d M Y') }}@if ($payment->reference) · {{ $payment->reference }}@endif</span>
                            @if ($payment->isVoided())<span class="badge ml-1 bg-slate-200 text-slate-700">Voided: {{ $payment->void_reason }}</span>@endif
                        </div>
                        @if (! $payment->isVoided())
                            @can('payments.record')<button type="button" wire:click="openVoid({{ $payment->id }})" class="text-red-600 hover:underline">Void</button>@endcan
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No payments recorded.</p>
                @endforelse
            </section>
        </div>

        <aside class="h-fit rounded-xl border border-slate-200 bg-white p-5">
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd>{{ number_format((float) $sale->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Discount{{ $sale->discount_type === 'percent' ? " ({$sale->discount_value}%)" : '' }}</dt><dd>− {{ number_format((float) $sale->discount_amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Tax ({{ rtrim(rtrim($sale->tax_rate, '0'), '.') ?: '0' }}%)</dt><dd>{{ number_format((float) $sale->tax_amount, 2) }}</dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total ({{ $currency }})</dt><dd>{{ number_format((float) $sale->total, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-600">Paid</dt><dd>{{ number_format((float) $sale->amount_paid, 2) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>Balance due</dt><dd>{{ number_format((float) $sale->balance(), 2) }}</dd></div>
                <div class="flex justify-between pt-2"><dt class="text-slate-600">Due date</dt><dd class="{{ $sale->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ $sale->due_date->format('d M Y') }}</dd></div>
            </dl>
            @if ($sale->notes)<p class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600">{{ $sale->notes }}</p>@endif
        </aside>
    </div>

    @if ($showPayment)
        <x-ui.modal title="Record payment" close="closeModals">
            <form wire:submit="recordPayment" class="space-y-4">
                <p class="text-sm text-slate-600">Balance due: <strong>{{ $currency }} {{ number_format((float) $sale->balance(), 2) }}</strong></p>
                <x-ui.field label="Amount" name="payAmount"><input id="payAmount" type="number" step="0.01" min="0" wire:model="payAmount" class="input" autofocus></x-ui.field>
                <x-ui.field label="Method" name="payMethod"><select id="payMethod" wire:model="payMethod" class="input">@foreach ($paymentMethods as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Date received" name="payDate"><input id="payDate" type="date" wire:model="payDate" max="{{ today()->toDateString() }}" class="input"></x-ui.field>
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

    @if ($showCancel)
        <x-ui.modal title="Cancel sale" close="closeModals">
            <form wire:submit="cancelSale" class="space-y-4">
                <p class="text-sm text-slate-600">Cancelling restores the sold stock. If payments were recorded, void them first. The invoice stays on record as cancelled.</p>
                <x-ui.field label="Reason (required)" name="cancelReason"><input id="cancelReason" type="text" wire:model="cancelReason" class="input" maxlength="255" autofocus></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Keep sale</button><button type="submit" class="btn-danger">Cancel sale</button></div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showEdit)
        <x-ui.modal title="Edit sale details" close="closeModals">
            <form wire:submit="saveEdit" class="space-y-4">
                <p class="text-xs text-slate-500">Items and amounts cannot be edited. To correct them, cancel this sale and create a new one.</p>
                <x-ui.field label="Due date" name="editDueDate"><input id="editDueDate" type="date" wire:model="editDueDate" class="input"></x-ui.field>
                <x-ui.field label="Notes" name="editNotes"><textarea id="editNotes" rows="3" wire:model="editNotes" class="input"></textarea></x-ui.field>
                <div class="flex justify-end gap-2"><button type="button" wire:click="closeModals" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </x-ui.modal>
    @endif
</div>
