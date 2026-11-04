@php
    $bar = \App\Settings\BarSettings::all();
    $barContact = trim(implode(' · ', array_filter([$bar['address'] ?? '', $bar['phone'] ?? ''])));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} — BMS</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #eef2f7; color: #0f172a; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 12px; }
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between;
            gap: 12px; padding: 10px 18px; background: #1e3a8a; color: #fff;
        }
        .toolbar h1 { font-size: 14px; margin: 0; font-weight: 700; letter-spacing: .02em; }
        .toolbar p { margin: 2px 0 0; font-size: 11px; opacity: .85; }
        .toolbar .actions { display: flex; gap: 8px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px;
            font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent;
        }
        .btn-primary { background: #fff; color: #1e3a8a; }
        .btn-ghost { background: rgba(255,255,255,.12); color: #fff; border-color: rgba(255,255,255,.35); }
        .page { max-width: 1000px; margin: 18px auto; background: #fff; padding: 28px 32px 36px; box-shadow: 0 1px 3px rgba(15,23,42,.12); }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1e3a8a; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { font-size: 20px; font-weight: 800; color: #1e3a8a; letter-spacing: -.01em; }
        .brand small { display: block; font-size: 11px; font-weight: 500; color: #64748b; letter-spacing: 0; margin-top: 2px; }
        .doc-meta { text-align: right; font-size: 11px; color: #475569; }
        .doc-meta strong { display: block; font-size: 14px; color: #0f172a; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; background: #f8fafc; }
        .card .label { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 700; }
        .card .value { font-size: 17px; font-weight: 800; color: #0f172a; margin-top: 4px; }
        .card .sub { font-size: 10px; color: #94a3b8; margin-top: 2px; }
        .card .value.up { color: #047857; }
        .card .value.down { color: #dc2626; }
        h2.section { font-size: 13px; margin: 22px 0 8px; color: #1e3a8a; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #1e3a8a; color: #fff; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; text-align: left; padding: 7px 8px; }
        td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; font-size: 11px; color: #1e293b; }
        tr:nth-child(even) td { background: #f8fafc; }
        tfoot td { font-weight: 700; background: #f1f5f9 !important; border-top: 2px solid #cbd5e1; }
        .ta-right { text-align: right; }
        .ta-center { text-align: center; }
        .empty { color: #94a3b8; font-style: italic; padding: 8px; }
        .doc-foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; display: flex; justify-content: space-between; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .page { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            @page { size: A4 landscape; margin: 12mm; }
        }
        @media (max-width: 720px) {
            .summary { grid-template-columns: repeat(2, 1fr); }
            .page { padding: 16px; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="toolbar">
        <div>
            <h1>{{ $report['title'] }}</h1>
            <p>Period: {{ $dateLabel }} · {{ $counterLabel }}</p>
        </div>
        <div class="actions">
            <button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
            <a class="btn btn-ghost" href="{{ url()->previous() }}">Back</a>
        </div>
    </div>

    <div class="page">
        <div class="doc-head">
            <div class="brand">
                {{ $bar['name'] }}
                <small>{{ $barContact !== '' ? $barContact : 'Bar & Restaurant Management System' }}</small>
            </div>
            <div class="doc-meta">
                <strong>{{ $report['title'] }}</strong>
                {{ $dateLabel }}<br>
                {{ $counterLabel }}<br>
                Generated {{ now()->format('d M Y, H:i') }}
            </div>
        </div>

        @if (!empty($report['summary']))
            <div class="summary">
                @foreach ($report['summary'] as $card)
                    <div class="card">
                        <div class="label">{{ $card['label'] }}</div>
                        <div class="value {{ $card['tone'] ?? '' }}">{{ \App\Reports\ReportData::format($card['value'], $card['format']) }}</div>
                        @if (!empty($card['sub']))<div class="sub">{{ $card['sub'] }}</div>@endif
                    </div>
                @endforeach
            </div>
        @endif

        @foreach ($report['sections'] as $section)
            <h2 class="section">{{ $section['title'] }}</h2>
            @if (empty($section['rows']))
                <p class="empty">No data for the selected filters.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            @foreach ($section['columns'] as $column)
                                <th class="ta-{{ $column['align'] }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['rows'] as $row)
                            <tr>
                                @foreach ($section['columns'] as $i => $column)
                                    <td class="ta-{{ $column['align'] }}">{{ \App\Reports\ReportData::format($row[$i] ?? '', $column['type']) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if (!empty($section['footer']))
                        <tfoot>
                            <tr>
                                @foreach ($section['columns'] as $i => $column)
                                    <td class="ta-{{ $column['align'] }}">{{ \App\Reports\ReportData::format($section['footer'][$i] ?? '', $column['type']) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        @endforeach

        <div class="doc-foot">
            <span>{{ $bar['name'] }} — {{ $report['title'] }}</span>
            <span>{{ $dateLabel }} · {{ $counterLabel }}</span>
        </div>
    </div>

</body>
</html>
