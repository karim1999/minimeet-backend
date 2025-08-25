@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'left',
    'href' => null,
    'type' => 'button',
    'disabled' => false
])

@php
$baseClasses = 'inline-flex items-center font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors duration-150 ease-in-out';

$variants = [
    'primary' => 'text-white bg-blue-600 hover:bg-blue-700 focus:ring-blue-500',
    'secondary' => 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:ring-blue-500',
    'success' => 'text-white bg-green-600 hover:bg-green-700 focus:ring-green-500',
    'danger' => 'text-white bg-red-600 hover:bg-red-700 focus:ring-red-500',
    'warning' => 'text-white bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500',
    'danger-outline' => 'text-red-700 bg-white border border-red-300 hover:bg-red-50 focus:ring-red-500',
];

$sizes = [
    'sm' => 'px-3 py-2 text-sm',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-4 py-2 text-base',
    'xl' => 'px-6 py-3 text-base',
];

$iconSizes = [
    'sm' => 'h-4 w-4',
    'md' => 'h-5 w-5',
    'lg' => 'h-5 w-5',
    'xl' => 'h-6 w-6',
];

$classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);

if ($disabled) {
    $classes .= ' opacity-50 cursor-not-allowed';
}

$iconClass = $iconSizes[$size] ?? $iconSizes['md'];
if ($iconPosition === 'left') {
    $iconClass .= ' mr-2 -ml-1';
} else {
    $iconClass .= ' ml-2 -mr-1';
}

$tag = $href ? 'a' : 'button';
@endphp

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled) aria-disabled="true" @endif>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled) disabled @endif>
@endif

    @if($icon && $iconPosition === 'left')
        <svg class="{{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            {!! $icon !!}
        </svg>
    @endif
    
    {{ $slot }}
    
    @if($icon && $iconPosition === 'right')
        <svg class="{{ $iconClass }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            {!! $icon !!}
        </svg>
    @endif

@if($href)
</a>
@else
</button>
@endif