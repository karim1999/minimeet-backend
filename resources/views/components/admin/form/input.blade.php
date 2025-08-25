@props([
    'label' => null,
    'name',
    'type' => 'text',
    'required' => false,
    'placeholder' => null,
    'help' => null,
    'error' => null,
    'value' => null,
    'disabled' => false
])

@php
$inputId = $name . '_' . uniqid();
$hasError = $error || $errors->has($name);
$errorMessage = $error ?: $errors->first($name);

$inputClasses = 'block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6';

if ($hasError) {
    $inputClasses .= ' ring-red-300 text-red-900 placeholder-red-300 focus:ring-red-500';
} else {
    $inputClasses .= ' ring-gray-300 focus:ring-blue-600';
}

if ($disabled) {
    $inputClasses .= ' bg-gray-50 text-gray-500';
}
@endphp

<div {{ $attributes->only('class') }}>
    @if($label)
    <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    @endif
    
    <div class="{{ $label ? 'mt-1' : '' }}">
        @if($type === 'textarea')
            <textarea 
                id="{{ $inputId }}"
                name="{{ $name }}"
                @if($required) required @endif
                @if($disabled) disabled @endif
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                {{ $attributes->except(['class', 'label', 'name', 'type', 'required', 'placeholder', 'help', 'error', 'value', 'disabled'])->merge(['class' => $inputClasses]) }}
                rows="4"
            >{{ old($name, $value) }}</textarea>
        @else
            <input 
                type="{{ $type }}"
                id="{{ $inputId }}"
                name="{{ $name }}"
                value="{{ old($name, $value) }}"
                @if($required) required @endif
                @if($disabled) disabled @endif
                @if($placeholder) placeholder="{{ $placeholder }}" @endif
                {{ $attributes->except(['class', 'label', 'name', 'type', 'required', 'placeholder', 'help', 'error', 'value', 'disabled'])->merge(['class' => $inputClasses]) }}
            >
        @endif
        
        @if($help && !$hasError)
        <p class="mt-2 text-sm text-gray-500">{{ $help }}</p>
        @endif
        
        @if($hasError)
        <p class="mt-2 text-sm text-red-600">{{ $errorMessage }}</p>
        @endif
    </div>
</div>