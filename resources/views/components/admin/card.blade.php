@props([
    'header' => null,
    'footer' => null,
    'padding' => 'default'
])

@php
$paddingClasses = [
    'none' => '',
    'sm' => 'p-4',
    'default' => 'px-4 py-5 sm:p-6',
    'lg' => 'px-6 py-8',
];

$cardPadding = $paddingClasses[$padding] ?? $paddingClasses['default'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white overflow-hidden shadow-sm rounded-lg']) }}>
    @if($header)
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
        @if(is_string($header))
            <h3 class="text-lg leading-6 font-medium text-gray-900">{{ $header }}</h3>
        @else
            {{ $header }}
        @endif
    </div>
    @endif
    
    <div class="{{ $cardPadding }}">
        {{ $slot }}
    </div>
    
    @if($footer)
    <div class="px-4 py-4 sm:px-6 bg-gray-50 border-t border-gray-200">
        {{ $footer }}
    </div>
    @endif
</div>