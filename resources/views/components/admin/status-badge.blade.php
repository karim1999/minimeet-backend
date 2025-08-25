@props([
    'status',
    'variant' => null,
    'size' => 'sm'
])

@php
$variants = [
    'active' => 'bg-green-100 text-green-800',
    'inactive' => 'bg-red-100 text-red-800', 
    'pending' => 'bg-yellow-100 text-yellow-800',
    'warning' => 'bg-yellow-100 text-yellow-800',
    'success' => 'bg-green-100 text-green-800',
    'error' => 'bg-red-100 text-red-800',
    'danger' => 'bg-red-100 text-red-800',
    'info' => 'bg-blue-100 text-blue-800',
    'primary' => 'bg-blue-100 text-blue-800',
    'secondary' => 'bg-gray-100 text-gray-800',
    'admin' => 'bg-purple-100 text-purple-800',
    'manager' => 'bg-blue-100 text-blue-800',
    'user' => 'bg-gray-100 text-gray-800',
    'member' => 'bg-gray-100 text-gray-800',
    'owner' => 'bg-indigo-100 text-indigo-800',
    'super_admin' => 'bg-purple-100 text-purple-800',
];

$sizes = [
    'xs' => 'px-2 py-0.5 text-xs',
    'sm' => 'px-2.5 py-0.5 text-xs',
    'md' => 'px-3 py-1 text-sm',
    'lg' => 'px-4 py-1 text-sm',
];

// Auto-detect variant from status if not provided
if (!$variant) {
    $statusLower = strtolower($status);
    if (array_key_exists($statusLower, $variants)) {
        $variant = $statusLower;
    } else {
        // Default mappings based on common patterns
        if (in_array($statusLower, ['true', '1', 'enabled', 'online', 'healthy'])) {
            $variant = 'active';
        } elseif (in_array($statusLower, ['false', '0', 'disabled', 'offline', 'unhealthy'])) {
            $variant = 'inactive';
        } else {
            $variant = 'secondary';
        }
    }
}

$badgeClasses = 'inline-flex items-center font-medium rounded-full ' . 
                ($variants[$variant] ?? $variants['secondary']) . ' ' .
                ($sizes[$size] ?? $sizes['sm']);
@endphp

<span {{ $attributes->merge(['class' => $badgeClasses]) }}>
    {{ ucfirst($status) }}
</span>