<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
@php
    $navy = '#261A66';
    $orange = '#EF5F18';
    $landscape = count($report['columns']) > 5;
    $pw = $landscape ? 842 : 595; // page width in points
    $isNum = fn ($col) => in_array($col['type'], ['money', 'number'], true);
    $headerSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$pw.'" height="118" viewBox="0 0 '.$pw.' 118">'
        .'<polygon points="0,0 330,0 262,98 0,98" fill="'.$navy.'"/>'
        .'<polygon points="330,0 354,0 286,98 262,98" fill="'.$orange.'"/>'
        .'<polygon points="354,0 380,0 312,98 286,98" fill="#F6B58C"/>'
        .'<polygon points="380,0 '.$pw.',0 '.$pw.',82 323,82" fill="#1B1250"/>'
        .'<polygon points="0,98 262,98 255,112 0,112" fill="'.$orange.'"/></svg>';
    $footerSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$pw.'" height="40" viewBox="0 0 '.$pw.' 40">'
        .'<polygon points="0,22 '.($pw * 0.58).',22 '.($pw * 0.58 - 8).',40 0,40" fill="'.$orange.'"/>'
        .'<polygon points="'.($pw * 0.78).',8 '.$pw.',8 '.$pw.',40 '.($pw * 0.78 - 26).',40" fill="'.$navy.'"/></svg>';
    $uri = fn ($svg) => 'data:image/svg+xml;base64,'.base64_encode($svg);
@endphp
    <style>
        @page { margin: 30pt 0 60pt 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1e293b; }
        .art-top { position: absolute; top: -30pt; left: 0; width: {{ $pw }}pt; height: 118pt; }
        .art-bottom { position: fixed; bottom: -60pt; left: 0; width: {{ $pw }}pt; height: 40pt; }
        .brand { position: absolute; top: 4pt; left: 36pt; width: 270pt; color: #ffffff; }
        .brand .name { font-size: 14pt; font-weight: bold; letter-spacing: 1.2pt; text-transform: uppercase; line-height: 1.25; }
        .brand .sub { margin-top: 4pt; font-size: 7.5pt; letter-spacing: 2pt; text-transform: uppercase; color: #F6B58C; }
        .title { position: absolute; top: 2pt; right: 36pt; width: {{ $pw - 400 }}pt; text-align: right; }
        .title .word { font-size: 19pt; font-weight: bold; letter-spacing: 1pt; color: {{ $orange }}; line-height: 1.15; }
        .title .sub { margin-top: 5pt; font-size: 8.5pt; color: #ffffff; }
        .page { padding: 100pt 36pt 0 36pt; }
        .meta td { padding: 7pt 11pt; background: #F5F3FB; }
        .meta .k { font-size: 7pt; letter-spacing: 1pt; text-transform: uppercase; color: #64748b; }
        .meta .v { margin-top: 2pt; font-size: 9pt; font-weight: bold; color: {{ $navy }}; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .data th { background: {{ $orange }}; color: #ffffff; text-align: left; font-size: 7.5pt; letter-spacing: 1pt; text-transform: uppercase; padding: 8pt 8pt; }
        .data td { padding: 7pt 8pt; border-bottom: 1.2pt solid {{ $navy }}; }
        .data tfoot td { background: {{ $orange }}; color: #ffffff; font-weight: bold; border-bottom: 0; padding: 8pt 8pt; }
        .r { text-align: right; }
        .page-footer { position: fixed; bottom: -15pt; left: 36pt; right: 36pt; font-size: 7pt; color: #64748b; }
        .page-footer .right { float: right; }
        .pagenum:after { content: "Page " counter(page); }
    </style>
</head>
<body>
<img class="art-top" src="{{ $uri($headerSvg) }}" alt="">
<img class="art-bottom" src="{{ $uri($footerSvg) }}" alt="">
<div class="page-footer"><span class="right pagenum"></span>{{ $company->name }} &middot; {{ $report['title'] }} &middot; Generated {{ now()->format('d M Y H:i') }} by BizCore ERP</div>

<div class="brand">
    <div class="name">{{ $company->name }}</div>
    <div class="sub">Business report</div>
</div>
<div class="title">
    <div class="word">{{ $report['title'] }}</div>
    <div class="sub">{{ $report['subtitle'] }}</div>
</div>

<div class="page">
    <table class="meta">
        <tr>
            <td style="width: 34%;"><div class="k">Company</div><div class="v">{{ $company->name }}</div></td>
            <td style="width: 28%;"><div class="k">Period</div><div class="v">{{ $report['subtitle'] }}</div></td>
            <td style="width: 16%;"><div class="k">Currency</div><div class="v">{{ $company->currency }}</div></td>
            <td><div class="k">Generated</div><div class="v">{{ now()->format('d M Y') }}</div></td>
        </tr>
    </table>

    <table class="data" style="margin-top: 14pt;">
        <thead><tr>@foreach ($report['columns'] as $col)<th class="{{ $isNum($col) ? 'r' : '' }}">{{ $col['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach ($report['columns'] as $key => $col)
                    <td class="{{ $isNum($col) ? 'r' : '' }}">{{ $col['type'] === 'money' && isset($row[$key]) ? number_format((float) $row[$key], 2) : ($row[$key] ?? '') }}</td>
                @endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" style="text-align:center;padding:18pt;color:#64748b">No data for this report.</td></tr>
            @endforelse
        </tbody>
        @if ($report['totals'])
            <tfoot><tr>@foreach ($report['columns'] as $key => $col)
                <td class="{{ $isNum($col) ? 'r' : '' }}">{{ $col['type'] === 'money' && isset($report['totals'][$key]) ? number_format((float) $report['totals'][$key], 2) : ($report['totals'][$key] ?? '') }}</td>
            @endforeach</tr></tfoot>
        @endif
    </table>
</div>
</body>
</html>
