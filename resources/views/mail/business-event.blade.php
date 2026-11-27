<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f5f3fb;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;">
    <tr><td style="background:#261a66;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:1px;">{{ strtoupper($company->name) }}</td></tr>
    <tr><td style="height:4px;background:#ef5f18;"></td></tr>
    <tr><td style="padding:24px;font-size:14px;line-height:1.6;">
        <p style="margin:0 0 4px;">Hello {{ $user->name }},</p>
        <h2 style="margin:16px 0 8px;font-size:18px;color:#261a66;">{{ $message['title'] }}</h2>
        <p style="margin:0;color:#475569;">{{ $message['body'] }}</p>
        @if (! empty($message['url']))
            <p style="margin:24px 0 0;"><a href="{{ $message['url'] }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;">Open in BIZCORE</a></p>
        @endif
        <p style="margin:24px 0 0;font-size:12px;color:#94a3b8;">You get this because of your role at {{ $company->name }}. Administrators can switch these emails on or off under Company.</p>
    </td></tr>
</table>
</body>
</html>
