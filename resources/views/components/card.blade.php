@props([
    'padding' => true,
    'hover' => false,
    'header' => null,
    'footer' => null,
])

<div {{ $attributes->merge([
    'class' => 'bg-white rounded-xl border border-gray-200 shadow-sm ' .
        ($hover ? 'hover:shadow-md hover:border-gray-300 transition-all duration-200 ' : '') .
        ($padding && !$header && !$footer ? 'p-6 ' : ''),
]) }}>
    @if ($header)
        <div class="px-6 py-4 border-b border-gray-100">
            {{ $header }}
        </div>
    @endif

    @if ($padding && ($header || $footer))
        <div class="px-6 py-5">
            {{ $slot }}
        </div>
    @elseif (!$padding && ($header || $footer))
        {{ $slot }}
    @elseif ($padding)
        {{ $slot }}
    @endif

    @if ($footer)
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
            {{ $footer }}
        </div>
    @endif
</div>
