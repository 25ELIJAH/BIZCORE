<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 32px 34px 48px 34px; }
        .page-footer { position: fixed; bottom: -30px; left: 0; right: 0; color: #64748b; font-size: 8px; border-top: 1px solid #cbd5e1; padding-top: 5px; }
        .page-footer .right { float: right; }
        .pagenum:after { content: "Page " counter(page); }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 17px; margin: 0 0 2px; color: #261A66; }
        .rule { border-top: 3px solid #261A66; margin: 6px 0 10px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #261A66; color: #ffffff; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; padding: 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #E8E4F4; }
        .r { text-align: right; }
        tfoot td { background: #FFF1E8; color: #261A66; font-weight: bold; border-top: 2px solid #261A66; border-bottom: 0; }
    </style>
</head>
<body>
    <div class="page-footer"><span class="right pagenum"></span>{{ $company->name }} · {{ $report['title'] }} · Generated {{ now()->format('Y-m-d H:i') }} by BizCore ERP</div>
    <h1>{{ $report['title'] }}</h1>
    <div class="meta">{{ $company->name }} · {{ $report['subtitle'] }} · Currency {{ $company->currency }}</div>
    <div class="rule"></div>
    <table>
        <thead><tr>@foreach ($report['columns'] as $col)<th class="{{ in_array($col['type'], ['money', 'number'], true) ? 'r' : '' }}">{{ $col['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach ($report['columns'] as $key => $col)
                    <td class="{{ in_array($col['type'], ['money', 'number'], true) ? 'r' : '' }}">{{ $col['type'] === 'money' && isset($row[$key]) ? number_format((float) $row[$key], 2) : ($row[$key] ?? '') }}</td>
                @endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" style="text-align:center;padding:16px;color:#64748b">No data for this report.</td></tr>
            @endforelse
        </tbody>
        @if ($report['totals'])
            <tfoot><tr>@foreach ($report['columns'] as $key => $col)
                <td class="{{ in_array($col['type'], ['money', 'number'], true) ? 'r' : '' }}">{{ $col['type'] === 'money' && isset($report['totals'][$key]) ? number_format((float) $report['totals'][$key], 2) : ($report['totals'][$key] ?? '') }}</td>
            @endforeach</tr></tfoot>
        @endif
    </table>
</body>
</html>
