@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'py-1 bg-white rounded-xl shadow-lg border border-gray-200',
])

@php
    $alignmentClasses = match ($align) {
        'left' => 'left-0 origin-top-left',
        'right' => 'right-0 origin-top-right',
        default => 'right-0 origin-top-right',
    };

    $widthClasses = match ($width) {
        '48' => 'w-48',
        '56' => 'w-56',
        '64' => 'w-64',
        default => 'w-48',
    };
@endphp

<div x-data="{ open: false }"
     @keydown.escape.window="open = false"
     @click.away="open = false"
     {{ $attributes->wire('key')->merge(['class' => 'relative']) }}>
    {{-- Trigger --}}
    <div @click="open = !open" class="cursor-pointer">
        {{ $trigger ?? $slot }}
    </div>

    {{-- Menu --}}
    <div x-show="open"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute z-50 mt-2 {{ $alignmentClasses }} {{ $widthClasses }}">
        <div class="{{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
