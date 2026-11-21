<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        .r { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #94a3b8; border-bottom: 0; }
        .foot { margin-top: 14px; color: #94a3b8; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <div class="meta">{{ $company->name }} · {{ $report['subtitle'] }} · Currency {{ $company->currency }}</div>
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
    <div class="foot">Generated {{ now()->format('Y-m-d H:i') }} by BizCore ERP</div>
</body>
</html>
