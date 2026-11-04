@props([
    'variant' => 'default',
    'size' => 'sm',
    'dot' => false,
])

@php
    $variants = [
        'default' => 'bg-gray-100 text-gray-700 ring-1 ring-gray-300/30',
        'success' => 'bg-green-50 text-green-700 ring-1 ring-green-600/20',
        'warning' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
        'danger' => 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
        'info' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20',
        'purple' => 'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20',
    ];

    $dotColors = [
        'default' => 'bg-gray-400',
        'success' => 'bg-green-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-red-500',
        'info' => 'bg-blue-500',
        'purple' => 'bg-purple-500',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-sm',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center font-medium rounded-full ' . $variants[$variant] . ' ' . $sizes[$size],
]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $dotColors[$variant] }}"></span>
    @endif
    {{ $slot }}
</span>
