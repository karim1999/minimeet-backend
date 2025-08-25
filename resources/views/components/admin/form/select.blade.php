@props([
    'label' => null,
    'name',
    'required' => false,
    'help' => null,
    'error' => null,
    'value' => null,
    'disabled' => false,
    'options' => [],
    'placeholder' => null
])

@php
$inputId = $name . '_' . uniqid();
$hasError = $error || $errors->has($name);
$errorMessage = $error ?: $errors->first($name);

$selectClasses = 'block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6';

if ($hasError) {
    $selectClasses .= ' ring-red-300 text-red-900 focus:ring-red-500';
} else {
    $selectClasses .= ' ring-gray-300 focus:ring-blue-600';
}

if ($disabled) {
    $selectClasses .= ' bg-gray-50 text-gray-500';
}

$selectedValue = old($name, $value);
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
        <select 
            id="{{ $inputId }}"
            name="{{ $name }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            {{ $attributes->except(['class', 'label', 'name', 'required', 'help', 'error', 'value', 'disabled', 'options', 'placeholder'])->merge(['class' => $selectClasses]) }}
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                @foreach($options as $optionValue => $optionLabel)
                    @if(is_array($optionLabel))
                        <option value="{{ $optionValue }}" {{ $selectedValue == $optionValue ? 'selected' : '' }} @if(isset($optionLabel['disabled']) && $optionLabel['disabled']) disabled @endif>
                            {{ $optionLabel['label'] ?? $optionValue }}
                        </option>
                    @else
                        <option value="{{ $optionValue }}" {{ $selectedValue == $optionValue ? 'selected' : '' }}>
                            {{ $optionLabel }}
                        </option>
                    @endif
                @endforeach
            @endif
        </select>
        
        @if($help && !$hasError)
        <p class="mt-2 text-sm text-gray-500">{{ $help }}</p>
        @endif
        
        @if($hasError)
        <p class="mt-2 text-sm text-red-600">{{ $errorMessage }}</p>
        @endif
    </div>
</div>