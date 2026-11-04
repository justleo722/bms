@props([
    'variant' => 'primary',
    'size' => 'md',
    'disabled' => false,
    'loading' => false,
    'icon' => null,
])

@php
    $base = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';

    $variants = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 active:bg-blue-800 focus:ring-blue-500 shadow-sm shadow-blue-200',
        'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 active:bg-gray-100 focus:ring-blue-500',
        'ghost' => 'text-gray-600 hover:text-gray-900 hover:bg-gray-100 active:bg-gray-200 focus:ring-gray-400',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 active:bg-red-800 focus:ring-red-500 shadow-sm shadow-red-200',
        'success' => 'bg-green-600 text-white hover:bg-green-700 active:bg-green-800 focus:ring-green-500 shadow-sm shadow-green-200',
        'outline' => 'bg-transparent text-blue-600 border border-blue-600 hover:bg-blue-50 active:bg-blue-100 focus:ring-blue-500',
    ];

    $sizes = [
        'xs' => 'px-2.5 py-1 text-xs gap-1.5',
        'sm' => 'px-3 py-1.5 text-sm gap-1.5',
        'md' => 'px-4 py-2 text-sm gap-2',
        'lg' => 'px-6 py-3 text-base gap-2',
    ];

    $iconSizes = [
        'xs' => 'w-3.5 h-3.5',
        'sm' => 'w-4 h-4',
        'md' => 'w-4 h-4',
        'lg' => 'w-5 h-5',
    ];
@endphp

<button {{ $attributes->merge([
    'class' => $base . ' ' . $variants[$variant] . ' ' . $sizes[$size],
    'disabled' => $disabled || $loading,
]) }}>
    @if ($loading)
        <svg class="animate-spin {{ $iconSizes[$size] }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @elseif ($icon)
        @heroicon($icon, $iconSizes[$size])
    @endif

    {{ $slot }}
</button>
