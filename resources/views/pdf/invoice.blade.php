<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice->invoice_number }}</title>
    <style>
        @page { margin: 34px 38px 52px 38px; }
        .page-footer { position: fixed; bottom: -32px; left: 0; right: 0; color: #94a3b8; font-size: 9px; border-top: 1px solid #e2e8f0; padding-top: 5px; }
        .page-footer .right { float: right; }
        .pagenum:after { content: "Page " counter(page); }
        .keep { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 22px; margin: 0 0 4px; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .items th { background: #f1f5f9; text-align: left; padding: 7px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
        .totals td { padding: 4px 8px; }
        .grand td { border-top: 2px solid #1e293b; font-size: 13px; font-weight: bold; padding-top: 7px; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .paid { background: #d1fae5; color: #047857; } .due { background: #e0f2fe; color: #0369a1; } .cancelled { background: #e2e8f0; color: #475569; } .overdue { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
<div class="page-footer"><span class="right pagenum"></span>{{ $company->name }} · Invoice {{ $sale->invoice->invoice_number }}</div>
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $label = $sale->isCancelled() ? ['Cancelled', 'cancelled'] : ($sale->isOverdue() ? ['Overdue', 'overdue'] : ($sale->payment_status === 'paid' ? ['Paid', 'paid'] : ($sale->payment_status === 'partial' ? ['Partially paid', 'due'] : ['Unpaid', 'due'])));
@endphp
<table>
    <tr>
        <td style="width:60%; vertical-align: top;">
            @if ($logo)<img src="{{ $logo }}" alt="" style="max-height: 55px; margin-bottom: 8px;"><br>@endif
            <strong style="font-size: 15px;">{{ $company->name }}</strong><br>
            <span class="muted">
                {!! nl2br(e($company->address)) !!}<br>
                @if ($company->email){{ $company->email }}<br>@endif
                @if ($company->phone){{ $company->phone }}<br>@endif
                @if ($company->registration_number)Reg. no. {{ $company->registration_number }}@endif
            </span>
        </td>
        <td class="right" style="vertical-align: top;">
            <h1>INVOICE</h1>
            <strong>{{ $sale->invoice->invoice_number }}</strong><br>
            <span class="badge {{ $label[1] }}">{{ $label[0] }}</span><br><br>
            <span class="muted">Issued:</span> {{ $sale->invoice->issue_date->format('d M Y') }}<br>
            <span class="muted">Due:</span> {{ $sale->invoice->due_date->format('d M Y') }}
        </td>
    </tr>
</table>

<p style="margin: 22px 0 4px;" class="muted">BILL TO</p>
<strong>{{ $sale->customer?->name ?? 'Walk-in customer' }}</strong><br>
@if ($sale->customer)
    <span class="muted">{!! nl2br(e($sale->customer->address)) !!}@if ($sale->customer->email)<br>{{ $sale->customer->email }}@endif @if ($sale->customer->tax_number)<br>Tax no. {{ $sale->customer->tax_number }}@endif</span>
@endif

<table class="items" style="margin-top: 18px;">
    <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Amount</th></tr></thead>
    <tbody>
    @foreach ($sale->items as $item)
        <tr>
            <td>{{ $item->product_name }}<br><span class="muted">{{ $item->sku }}</span></td>
            <td class="right">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
            <td class="right">{{ $money($item->unit_price) }}</td>
            <td class="right">{{ $money($item->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="keep">
<table class="totals" style="width: 55%; margin-left: 45%; margin-top: 10px;">
    <tr><td>Subtotal</td><td class="right">{{ $money($sale->subtotal) }}</td></tr>
    @if (bccomp((string) $sale->discount_amount, '0', 2) > 0)
        <tr><td>Discount{{ $sale->discount_type === 'percent' ? ' ('.rtrim(rtrim($sale->discount_value, '0'), '.').'%)' : '' }}</td><td class="right">− {{ $money($sale->discount_amount) }}</td></tr>
    @endif
    @if (bccomp((string) $sale->tax_rate, '0', 2) > 0)
        <tr><td>Tax ({{ rtrim(rtrim($sale->tax_rate, '0'), '.') }}%)</td><td class="right">{{ $money($sale->tax_amount) }}</td></tr>
    @endif
    <tr class="grand"><td>Total ({{ $company->currency }})</td><td class="right">{{ $money($sale->total) }}</td></tr>
    <tr><td>Amount paid</td><td class="right">{{ $money($sale->amount_paid) }}</td></tr>
    <tr><td><strong>Balance due</strong></td><td class="right"><strong>{{ $money($sale->balance()) }}</strong></td></tr>
</table>

@if ($sale->payments->isNotEmpty())
    <p class="muted" style="margin: 18px 0 4px;">PAYMENTS RECEIVED</p>
    @foreach ($sale->payments as $payment)
        {{ $payment->paid_at->format('d M Y') }} · {{ $methods[$payment->method] ?? $payment->method }} · {{ $money($payment->amount) }}@if ($payment->reference) · Ref {{ $payment->reference }}@endif<br>
    @endforeach
@endif

</div>

@if ($sale->notes)<p class="muted" style="margin-top: 18px;">Notes: {{ $sale->notes }}</p>@endif
@if ($sale->isCancelled())<p style="margin-top: 18px; color: #b91c1c;"><strong>This invoice was cancelled</strong>{{ $sale->cancel_reason ? ': '.$sale->cancel_reason : '' }}.</p>@endif
</body>
</html>
