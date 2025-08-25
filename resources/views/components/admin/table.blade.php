@props([
    'headers' => [],
    'rows' => [],
    'actions' => null,
    'searchable' => false,
    'sortable' => false,
    'pagination' => null
])

<div {{ $attributes->merge(['class' => 'bg-white overflow-hidden shadow-sm rounded-lg']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            @if($headers)
            <thead class="bg-gray-50">
                <tr>
                    @foreach($headers as $header)
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            @if(is_array($header))
                                {{ $header['label'] }}
                                @if($sortable && isset($header['sortable']) && $header['sortable'])
                                    <button class="ml-1 text-gray-400 hover:text-gray-600">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                        </svg>
                                    </button>
                                @endif
                            @else
                                {{ $header }}
                            @endif
                        </th>
                    @endforeach
                    @if($actions)
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    @endif
                </tr>
            </thead>
            @endif
            
            <tbody class="bg-white divide-y divide-gray-200">
                {{ $slot }}
            </tbody>
        </table>
    </div>
    
    @if($pagination)
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $pagination }}
        </div>
    @endif
</div>