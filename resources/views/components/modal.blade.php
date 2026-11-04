@props([
    'name' => 'modal',
    'title' => null,
    'maxWidth' => 'lg',
    'closeable' => true,
])

@php
    $maxWidthClasses = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
    ];
@endphp

<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
     x-on:keydown.escape.window="if (show && {{ $closeable ? 'true' : 'false' }}) show = false"
     x-show="show"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm"
         x-show="show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="if ({{ $closeable ? 'true' : 'false' }}) show = false">
    </div>

    {{-- Panel --}}
    <div class="flex items-center justify-center min-h-full p-4">
        <div x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative bg-white rounded-2xl shadow-2xl border border-gray-200 w-full {{ $maxWidthClasses[$maxWidth] }}">

            {{-- Header --}}
            @if ($title || isset($header))
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $title ?? $header }}</h3>
                    @if ($closeable)
                        <button @click="show = false" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            @heroicon('o-x-mark', 'w-5 h-5')
                        </button>
                    @endif
                </div>
            @endif

            {{-- Body --}}
            <div class="px-6 py-5">
                {{ $slot }}
            </div>

            {{-- Footer --}}
            @isset($footer)
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/80 rounded-b-2xl">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
