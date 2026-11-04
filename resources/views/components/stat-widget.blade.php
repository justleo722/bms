@props([
    'label',
    'value',
    'icon',
    'trend' => null,
    'trendDirection' => 'up',
    'trendLabel' => null,
    'color' => 'blue',
])

@php
    $colors = [
        'blue' => 'bg-blue-50 text-blue-600',
        'green' => 'bg-green-50 text-green-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red' => 'bg-red-50 text-red-600',
        'purple' => 'bg-purple-50 text-purple-600',
        'cyan' => 'bg-cyan-50 text-cyan-600',
        'gray' => 'bg-gray-100 text-gray-600',
    ];

    $trendColors = [
        'up' => 'text-green-600',
        'down' => 'text-red-600',
        'neutral' => 'text-gray-500',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 shadow-sm p-6']) }}>
    <div class="flex items-center justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-500 truncate">{{ $label }}</p>
            <p class="mt-1.5 text-2xl font-bold text-gray-900 tracking-tight">{{ $value }}</p>
            @if ($trend || $trendLabel)
                <div class="flex items-center gap-1.5 mt-1.5">
                    @if ($trend)
                        <span class="inline-flex items-center gap-0.5 text-sm font-medium {{ $trendColors[$trendDirection] }}">
                            @heroicon($trendDirection === 'up' ? 'o-arrow-trending-up' : ($trendDirection === 'down' ? 'o-arrow-trending-down' : 'o-minus'), 'w-4 h-4')
                            {{ $trend }}
                        </span>
                    @endif
                    @if ($trendLabel)
                        <span class="text-sm text-gray-400">{{ $trendLabel }}</span>
                    @endif
                </div>
            @endif
        </div>
        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center {{ $colors[$color] }}">
            @heroicon($icon, 'w-6 h-6')
        </div>
    </div>
</div>
