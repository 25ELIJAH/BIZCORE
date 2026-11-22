<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f5f3fb;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">
    <tr><td style="background:#261a66;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:1px;">{{ strtoupper($company->name) }}</td></tr>
    <tr><td style="height:4px;background:#ef5f18;"></td></tr>
    <tr><td style="padding:24px;font-size:14px;line-height:1.6;">
        <p>Dear {{ $sale->customer?->contact_person ?: ($sale->customer?->name ?? 'customer') }},</p>
        @if ($reminder)
            <p>This is a friendly reminder that invoice <strong>{{ $sale->invoice->invoice_number }}</strong> was due on <strong>{{ $sale->invoice->due_date->format('d M Y') }}</strong> and still has a balance of <strong>{{ $company->currency }} {{ number_format((float) $sale->balance(), 2) }}</strong>.</p>
            <p>If you have already paid, please ignore this message and accept our thanks.</p>
        @else
            <p>Please find attached invoice <strong>{{ $sale->invoice->invoice_number }}</strong> for <strong>{{ $company->currency }} {{ number_format((float) $sale->total, 2) }}</strong>, due on <strong>{{ $sale->invoice->due_date->format('d M Y') }}</strong>.</p>
        @endif
        @if ($company->setting('bank_account_number'))
            <p style="margin:16px 0 4px;"><strong>How to pay</strong></p>
            <p style="margin:0;color:#475569;">
                {{ $company->setting('bank_name') }}@if ($company->setting('bank_branch')) &middot; {{ $company->setting('bank_branch') }}@endif<br>
                Account name: {{ $company->setting('bank_account_name') ?: $company->name }}<br>
                Account no.: {{ $company->setting('bank_account_number') }}
            </p>
        @endif
        <p style="margin-top:20px;">Thank you for your business.<br>{{ $company->name }}@if ($company->phone)<br>{{ $company->phone }}@endif</p>
    </td></tr>
</table>
</body>
</html>
