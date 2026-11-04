@extends('layouts.master', ['activeNav' => $activeNav ?? 'reports'])

@section('title', 'Reports')

@php
    $reportTypes = $reportTypes ?? \App\Reports\ReportData::types();
    $reports = $reports ?? [];
    $filters = $filters ?? ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'counter' => 'all'];
    $counters = $counters ?? [];

    $typeKeys = array_keys($reportTypes);
    $requested = request()->query('type');
    $initialReport = in_array($requested, $typeKeys, true) ? $requested : ($typeKeys[0] ?? 'sales');

    $toneClass = [
        'up' => 'text-emerald-600',
        'down' => 'text-red-600',
        'neutral' => 'text-gray-500',
    ];
@endphp

@section('content')
<div x-data="{
        activeReport: @js($initialReport),
        exportUrl(kind) {
            const params = new URLSearchParams({
                type: this.activeReport,
                from: @js($filters['from']),
                to: @js($filters['to']),
                counter: @js($filters['counter']),
            });
            return @js(url('/reports/export')) + '/' + kind + '?' + params.toString();
        },
        printReport() {
            // Render the print view in a hidden iframe so the browser's print /
            // save-as-PDF dialog opens in place instead of a new tab.
            const url = this.exportUrl('pdf');
            const old = document.getElementById('report-print-frame');
            if (old) { old.remove(); }
            const frame = document.createElement('iframe');
            frame.id = 'report-print-frame';
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            frame.src = url;
            document.body.appendChild(frame);
        }
    }" class="pb-6">

    {{-- Header --}}
    <div class="mb-5 flex flex-col gap-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900 tracking-tight sm:text-2xl">Reports</h1>
            <p class="mt-1 text-sm text-gray-500">Operational, sales and financial reports across the bar &amp; restaurant.</p>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:gap-3">
            <button type="button" @click="printReport()"
               class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 sm:h-9 sm:px-4">
                @heroicon('o-printer', 'w-4 h-4')
                Print / PDF
            </button>
            <a :href="exportUrl('excel')"
               class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 sm:h-9 sm:px-4">
                @heroicon('o-arrow-down-tray', 'w-4 h-4')
                Export Excel
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ url('/reports') }}" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                <label class="text-xs font-medium text-gray-500">From:</label>
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
            </div>
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                <label class="text-xs font-medium text-gray-500">To:</label>
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
            </div>
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
                <label class="text-xs font-medium text-gray-500">Counter:</label>
                <select name="counter" class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 text-sm outline-none focus:border-blue-500 sm:h-9 sm:w-auto">
                    <option value="all" @selected($filters['counter'] === 'all')>All Counters</option>
                    @foreach ($counters as $counter)
                        <option value="{{ $counter }}" @selected($filters['counter'] === $counter)>{{ $counter }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="type" :value="activeReport">
            <button type="submit"
                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 sm:h-9">
                @heroicon('o-funnel', 'w-4 h-4')
                Apply
            </button>
            <span class="text-xs text-gray-400 sm:ml-auto">
                Showing <span class="font-medium text-gray-600">{{ $filters['from'] }}</span> to
                <span class="font-medium text-gray-600">{{ $filters['to'] }}</span>
                · {{ $filters['counter'] === 'all' ? 'All Counters' : $filters['counter'] }}
            </span>
        </div>
    </form>

    {{-- Report Type Tabs --}}
    <div class="flex items-center gap-1 mb-6 overflow-x-auto no-scrollbar border-b border-gray-200">
        @foreach ($reportTypes as $key => $meta)
            <button @click="activeReport = @js($key)"
                    :class="activeReport === @js($key) ? 'border-b-2 border-blue-600 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'"
                    class="inline-flex items-center gap-1.5 px-4 py-3 text-sm font-medium transition-all -mb-px whitespace-nowrap">
                <span>{{ $meta['title'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Report Panels --}}
    @foreach ($reports as $key => $report)
        <div x-show="activeReport === @js($key)" x-cloak>
            <p class="text-sm text-gray-500 mb-4">{{ $report['description'] ?? '' }}</p>

            {{-- Summary Cards --}}
            @if (!empty($report['summary']))
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    @foreach ($report['summary'] as $card)
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                            <p class="text-xs text-gray-500 mb-1">{{ $card['label'] }}</p>
                            <p class="text-xl font-bold {{ $card['format'] === 'text' ? 'text-gray-900' : ($toneClass[$card['tone'] ?? 'neutral'] ?? 'text-gray-900') }}">
                                {{ \App\Reports\ReportData::format($card['value'], $card['format']) }}
                            </p>
                            @if (!empty($card['sub']))
                                <span class="text-[10px] font-medium {{ $toneClass[$card['tone'] ?? 'neutral'] ?? 'text-gray-500' }}">
                                    {{ $card['sub'] }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Sections --}}
            <div class="space-y-6">
                @foreach ($report['sections'] as $section)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $section['title'] }}</h3>
                        </div>

                        @if (empty($section['rows']))
                            <p class="px-5 py-6 text-sm text-gray-400 italic">No data for the selected filters.</p>
                        @else
                            <div class="scroll-x overflow-x-auto">
                                <table class="w-full text-sm" style="min-width: {{ max(440, count($section['columns']) * 130) }}px">
                                    <thead>
                                        <tr class="border-b border-gray-100 bg-gray-50/50">
                                            @foreach ($section['columns'] as $column)
                                                <th class="px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase {{ $column['align'] === 'right' ? 'text-right' : 'text-left' }}">
                                                    {{ $column['label'] }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach ($section['rows'] as $row)
                                            <tr class="hover:bg-gray-50/40">
                                                @foreach ($section['columns'] as $i => $column)
                                                    <td class="px-5 py-3 {{ $column['align'] === 'right' ? 'text-right' : 'text-left' }}
                                                        {{ $i === 0 ? 'font-medium text-gray-900' : 'text-gray-600' }}">
                                                        {{ \App\Reports\ReportData::format($row[$i] ?? '', $column['type']) }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    @if (!empty($section['footer']))
                                        <tfoot>
                                            <tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold text-gray-900">
                                                @foreach ($section['columns'] as $i => $column)
                                                    <td class="px-5 py-3 {{ $column['align'] === 'right' ? 'text-right' : 'text-left' }}">
                                                        {{ \App\Reports\ReportData::format($section['footer'][$i] ?? '', $column['type']) }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
