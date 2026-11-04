@props([
    'icon' => 'o-inbox',
    'title' => 'No data available',
    'description' => null,
    'action' => null,
    'actionLabel' => 'Add new',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 px-6 text-center']) }}>
    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mb-4">
        @heroicon($icon, 'w-8 h-8 text-gray-400')
    </div>
    <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1.5 text-sm text-gray-500 max-w-sm">{{ $description }}</p>
    @endif
    @if ($action)
        <div class="mt-5">
            {{ $action }}
        </div>
    @elseif ($actionLabel)
        <button class="mt-5 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-sm shadow-blue-200">
            @heroicon('o-plus', 'w-4 h-4')
            {{ $actionLabel }}
        </button>
    @endif
</div>
