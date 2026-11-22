<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice->invoice_number }}</title>
@php
    $navy = '#261A66';
    $orange = '#EF5F18';
    $money = fn ($v) => number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.') ?: '0';
    $label = $sale->isCancelled() ? ['Cancelled', '#64748b'] : ($sale->isOverdue() ? ['Overdue', '#dc2626'] : ($sale->payment_status === 'paid' ? ['Paid', '#16a34a'] : ($sale->payment_status === 'partial' ? ['Partially paid', $orange] : ['Unpaid', $orange])));
    $headerSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="595" height="165" viewBox="0 0 595 165">'
        .'<polygon points="0,0 350,0 270,132 0,132" fill="'.$navy.'"/>'
        .'<polygon points="350,0 376,0 296,132 270,132" fill="'.$orange.'"/>'
        .'<polygon points="376,0 404,0 324,132 296,132" fill="#F6B58C"/>'
        .'<polygon points="404,0 595,0 595,116 331,116" fill="#1B1250"/>'
        .'<polygon points="0,132 270,132 260,150 0,150" fill="'.$orange.'"/></svg>';
    $footerSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="595" height="52" viewBox="0 0 595 52">'
        .'<polygon points="0,30 352,30 343,52 0,52" fill="'.$orange.'"/>'
        .'<polygon points="455,14 595,14 595,52 421,52" fill="'.$navy.'"/></svg>';
    $uri = fn ($svg) => 'data:image/svg+xml;base64,'.base64_encode($svg);
@endphp
    <style>
        @page { margin: 0 0 54pt 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; }
        .art-top { position: absolute; top: 0; left: 0; width: 595pt; height: 165pt; }
        .art-bottom { position: fixed; bottom: -54pt; left: 0; width: 595pt; height: 52pt; }
        .brand { position: absolute; top: 40pt; left: 42pt; width: 290pt; color: #ffffff; }
        .brand .name { font-size: 17pt; font-weight: bold; letter-spacing: 1.5pt; text-transform: uppercase; line-height: 1.25; }
        .brand .sub { margin-top: 5pt; font-size: 8.5pt; letter-spacing: 2pt; text-transform: uppercase; color: #F6B58C; }
        .brand img { max-height: 46pt; max-width: 200pt; margin-bottom: 6pt; }
        .title { position: absolute; top: 36pt; right: 42pt; width: 220pt; text-align: right; }
        .title .word { font-size: 31pt; font-weight: bold; letter-spacing: 2pt; color: {{ $orange }}; line-height: 1; }
        .title .no { margin-top: 8pt; font-size: 9.5pt; letter-spacing: 1pt; color: #ffffff; }
        .title .badge { display: inline-block; margin-top: 8pt; padding: 3pt 10pt; font-size: 8pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; color: #ffffff; }
        .page { padding: 190pt 42pt 0 42pt; }
        .chip { display: inline-block; padding: 4pt 10pt; background: {{ $navy }}; color: #ffffff; font-size: 8.5pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; border-bottom: 2pt solid {{ $orange }}; }
        .who strong { font-size: 11pt; }
        .muted { color: #64748b; line-height: 1.55; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 8pt 12pt; background: #F5F3FB; width: 25%; }
        .meta .k { font-size: 7.5pt; letter-spacing: 1pt; text-transform: uppercase; color: #64748b; }
        .meta .v { margin-top: 2pt; font-size: 10pt; font-weight: bold; color: {{ $navy }}; }
        thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .items th { background: {{ $orange }}; color: #ffffff; padding: 9pt 10pt; font-size: 8.5pt; letter-spacing: 1pt; text-transform: uppercase; text-align: left; }
        .items td { padding: 9pt 10pt; border-bottom: 1.4pt solid {{ $navy }}; }
        .items .sku { color: #64748b; font-size: 8pt; }
        .r { text-align: right; } .c { text-align: center; }
        .totals td { padding: 5pt 10pt; }
        .totals .grand td { background: {{ $orange }}; color: #ffffff; font-size: 11.5pt; font-weight: bold; padding: 8pt 10pt; }
        .keep { page-break-inside: avoid; }
        .line { border-top: 1.4pt solid {{ $navy }}; width: 150pt; margin-left: auto; padding-top: 4pt; text-align: center; font-size: 8pt; letter-spacing: 1pt; text-transform: uppercase; color: #64748b; }
    </style>
</head>
<body>
<img class="art-top" src="{{ $uri($headerSvg) }}" alt="">
<img class="art-bottom" src="{{ $uri($footerSvg) }}" alt="">

<div class="brand">
    @if ($logo)<img src="{{ $logo }}" alt=""><br>@endif
    <div class="name">{{ $company->name }}</div>
    @if ($company->registration_number)<div class="sub">Reg. no. {{ $company->registration_number }}</div>@endif
</div>
<div class="title">
    <div class="word">INVOICE</div>
    <div class="no">NO : {{ $sale->invoice->invoice_number }}</div>
    <span class="badge" style="background: {{ $label[1] }};">{{ $label[0] }}</span>
</div>

<div class="page">
    <table>
        <tr>
            <td style="width: 52%; vertical-align: top;" class="who">
                <span class="chip">Invoice to</span>
                <div style="margin-top: 9px;"><strong>{{ $sale->customer?->name ?? 'Walk-in customer' }}</strong></div>
                @if ($sale->customer)
                    <div class="muted">
                        @if ($sale->customer->contact_person){{ $sale->customer->contact_person }}<br>@endif
                        @if ($sale->customer->address){!! nl2br(e($sale->customer->address)) !!}<br>@endif
                        @if ($sale->customer->phone)Phone : {{ $sale->customer->phone }}<br>@endif
                        @if ($sale->customer->email)Email : {{ $sale->customer->email }}<br>@endif
                        @if ($sale->customer->tax_number)Tax no. : {{ $sale->customer->tax_number }}@endif
                    </div>
                @endif
            </td>
            <td style="vertical-align: top; text-align: right;" class="who">
                <span class="chip">Invoice from</span>
                <div style="margin-top: 9px;"><strong>{{ $company->name }}</strong></div>
                <div class="muted">
                    @if ($company->address){!! nl2br(e($company->address)) !!}<br>@endif
                    @if ($company->phone)Phone : {{ $company->phone }}<br>@endif
                    @if ($company->email)Email : {{ $company->email }}<br>@endif
                    @if ($company->registration_number)Reg. no. : {{ $company->registration_number }}@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="meta" style="margin-top: 16px;">
        <tr>
            <td><div class="k">Issue date</div><div class="v">{{ $sale->invoice->issue_date->format('d M Y') }}</div></td>
            <td><div class="k">Due date</div><div class="v">{{ $sale->invoice->due_date->format('d M Y') }}</div></td>
            <td><div class="k">Status</div><div class="v" style="color: {{ $label[1] }};">{{ $label[0] }}</div></td>
            <td><div class="k">Currency</div><div class="v">{{ $company->currency }}</div></td>
        </tr>
    </table>

    <table class="items" style="margin-top: 16px;">
        <thead><tr><th style="width: 50%;">Description</th><th class="c">Qty</th><th class="r">Unit price</th><th class="r">Total</th></tr></thead>
        <tbody>
        @foreach ($sale->items as $item)
            <tr>
                <td>{{ $item->product_name }}@if ($item->sku)<br><span class="sku">{{ $item->sku }}</span>@endif</td>
                <td class="c">{{ $qty($item->quantity) }}</td>
                <td class="r">{{ $money($item->unit_price) }}</td>
                <td class="r">{{ $money($item->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="keep">
    <table style="margin-top: 14px;">
        <tr>
            <td style="width: 52%; vertical-align: top; padding-right: 18px;">
                <span class="chip">Payment</span>
                <div class="muted" style="margin-top: 8px;">
                    @forelse ($sale->payments as $payment)
                        {{ $payment->paid_at->format('d M Y') }} &middot; {{ $methods[$payment->method] ?? $payment->method }} &middot; {{ $money($payment->amount) }}@if ($payment->reference) &middot; Ref {{ $payment->reference }}@endif<br>
                    @empty
                        No payment received yet.<br>
                    @endforelse
                    <span style="color:#64748b;">Accepted: {{ implode(', ', array_values($methods)) }}</span>
                </div>
            </td>
            <td style="vertical-align: top;">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="r">{{ $money($sale->subtotal) }}</td></tr>
                    @if (bccomp((string) $sale->discount_amount, '0', 2) > 0)
                        <tr><td>Discount{{ $sale->discount_type === 'percent' ? ' ('.rtrim(rtrim($sale->discount_value, '0'), '.').'%)' : '' }}</td><td class="r">&minus; {{ $money($sale->discount_amount) }}</td></tr>
                    @endif
                    @if (bccomp((string) $sale->tax_rate, '0', 2) > 0)
                        <tr><td>Tax ({{ rtrim(rtrim($sale->tax_rate, '0'), '.') }}%)</td><td class="r">{{ $money($sale->tax_amount) }}</td></tr>
                    @endif
                    <tr class="grand"><td>TOTAL ({{ $company->currency }})</td><td class="r">{{ $money($sale->total) }}</td></tr>
                    <tr><td>Amount paid</td><td class="r">{{ $money($sale->amount_paid) }}</td></tr>
                    <tr><td><strong>Balance due</strong></td><td class="r"><strong>{{ $money($sale->balance()) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 18px;">
        <tr>
            <td style="vertical-align: bottom;">
                <div style="font-size: 11px; font-weight: bold; color: {{ $navy }};">Thanks for your business</div>
                @if ($sale->notes)<div class="muted" style="margin-top: 4px; max-width: 300px;">{{ $sale->notes }}</div>@endif
                @if ($sale->isCancelled())<div style="margin-top: 6px; color: #b91c1c;"><strong>This invoice was cancelled</strong>{{ $sale->cancel_reason ? ': '.$sale->cancel_reason : '' }}.</div>@endif
            </td>
            <td style="vertical-align: bottom; width: 190px;">
                <div style="height: 34px;"></div>
                <div class="line">Authorised signature</div>
            </td>
        </tr>
    </table>
    </div>
</div>
</body>
</html>
