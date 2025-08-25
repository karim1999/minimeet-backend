@props([
    'title',
    'subtitle' => null,
    'actions' => null,
    'breadcrumbs' => null
])

<div {{ $attributes->merge(['class' => 'border-b border-gray-200 pb-5']) }}>
    @if($breadcrumbs)
    <nav class="mb-4" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            @foreach($breadcrumbs as $index => $breadcrumb)
                @if($loop->last)
                    <li class="font-medium text-gray-900">{{ $breadcrumb['text'] }}</li>
                @else
                    <li>
                        @if(isset($breadcrumb['url']))
                            <a href="{{ $breadcrumb['url'] }}" class="hover:text-gray-700">{{ $breadcrumb['text'] }}</a>
                        @else
                            {{ $breadcrumb['text'] }}
                        @endif
                        <svg class="ml-2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>
    @endif
    
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">{{ $title }}</h2>
            @if($subtitle)
            <p class="mt-2 max-w-4xl text-sm text-gray-500">
                {{ $subtitle }}
            </p>
            @endif
        </div>
        
        @if($actions)
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            @if(is_array($actions))
                <div class="flex space-x-3">
                    @foreach($actions as $action)
                        {!! $action !!}
                    @endforeach
                </div>
            @else
                {!! $actions !!}
            @endif
        </div>
        @endif
    </div>
</div>