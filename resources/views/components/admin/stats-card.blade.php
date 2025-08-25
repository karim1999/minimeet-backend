@props([
    'title',
    'value',
    'subtitle' => null,
    'icon' => null,
    'color' => 'blue',
    'trend' => null,
    'trendLabel' => null,
    'link' => null
])

@php
$colorClasses = [
    'blue' => 'text-blue-600',
    'green' => 'text-green-600',
    'red' => 'text-red-600',
    'yellow' => 'text-yellow-600',
    'purple' => 'text-purple-600',
    'indigo' => 'text-indigo-600',
    'gray' => 'text-gray-600',
];

$iconClass = $colorClasses[$color] ?? $colorClasses['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6']) }}>
    <dt class="truncate text-sm font-medium text-gray-500">{{ $title }}</dt>
    <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $value }}</dd>
    
    @if($subtitle || $trend || $icon)
    <div class="mt-2 flex items-center text-sm">
        @if($icon)
            <span class="{{ $iconClass }}">
                {!! $icon !!}
            </span>
        @endif
        
        @if($trend)
            <span class="font-medium {{ $iconClass }}">{{ $trend }}</span>
        @endif
        
        @if($trendLabel)
            <span class="ml-1 text-gray-500">{{ $trendLabel }}</span>
        @endif
        
        @if($subtitle && !$trend && !$trendLabel)
            <span class="text-gray-500">{{ $subtitle }}</span>
        @endif
    </div>
    @endif
    
    @if($link)
        <div class="mt-3">
            <a href="{{ $link['url'] }}" class="text-sm font-medium {{ $iconClass }} hover:opacity-75">
                {{ $link['text'] }} →
            </a>
        </div>
    @endif
</div>