@extends('layouts.admin')

@section('content')
<div x-data="activityManager" class="space-y-6">
    <!-- Header -->
    <div class="border-b border-gray-200 pb-5">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="text-xl font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                </div>
                <div>
                    <h2 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">User Activity</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Activity log for <strong>{{ $user->name }}</strong> in {{ $tenant->id }}
                    </p>
                </div>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-3">
                <a href="{{ route('admin.tenant-users.show', ['id' => $tenant->id . ':' . $user->id]) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    View User
                </a>
                <a href="{{ route('admin.tenant-users.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Users
                </a>
            </div>
        </div>
    </div>

    <!-- Activity Stats & Filters -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <!-- Activity Statistics -->
        <div class="lg:col-span-1">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Activity Summary</h3>
                    
                    @if(!empty($activityStats))
                    <div class="space-y-4">
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $activityStats['total_activities'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">Total Activities</div>
                        </div>
                        
                        <div class="text-center p-3 bg-green-50 rounded-lg">
                            <div class="text-xl font-bold text-green-600">{{ $activityStats['avg_daily_activities'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">Daily Average</div>
                        </div>
                        
                        @if($activityStats['last_activity'])
                        <div class="text-center p-3 bg-purple-50 rounded-lg">
                            <div class="text-sm font-medium text-purple-600">{{ \Carbon\Carbon::parse($activityStats['last_activity'])->diffForHumans() }}</div>
                            <div class="text-xs text-gray-500">Last Activity</div>
                        </div>
                        @endif
                    </div>
                    
                    @if(!empty($activityStats['activities_by_action']))
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Activity Breakdown</h4>
                        <div class="space-y-2">
                            @foreach($activityStats['activities_by_action'] as $action => $count)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ ucfirst(str_replace('_', ' ', $action)) }}</span>
                                <span class="font-medium">{{ $count }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @else
                    <p class="text-sm text-gray-500">No activity data available.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="lg:col-span-3">
            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <label for="action_filter" class="block text-sm font-medium text-gray-700">Filter by Action</label>
                        <select id="action_filter" 
                                x-model="filters.action_filter"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                            <option value="">All Actions</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                            <option value="created">Created</option>
                            <option value="updated">Updated</option>
                            <option value="suspended">Suspended</option>
                            <option value="activated">Activated</option>
                        </select>
                    </div>
                    
                    <div class="flex-1">
                        <label for="days" class="block text-sm font-medium text-gray-700">Time Range</label>
                        <select id="days" 
                                x-model="filters.days"
                                @change="applyFilters"
                                class="mt-1 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                        </select>
                    </div>
                    
                    <div class="flex-shrink-0 flex items-end">
                        <button @click="refreshActivities" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Activity Timeline</h3>
                        <span class="text-sm text-gray-500">
                            Showing activities from last {{ $days }} days
                        </span>
                    </div>
                    
                    @if($activities->count() > 0)
                    <div class="flow-root">
                        <ul class="-mb-8">
                            @foreach($activities as $index => $activity)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                    <span class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex items-start space-x-3">
                                        <div class="relative">
                                            <div class="h-10 w-10 rounded-full flex items-center justify-center
                                                {{ str_contains(strtolower($activity->action), 'login') ? 'bg-green-500' : '' }}
                                                {{ str_contains(strtolower($activity->action), 'logout') ? 'bg-yellow-500' : '' }}
                                                {{ str_contains(strtolower($activity->action), 'created') ? 'bg-blue-500' : '' }}
                                                {{ str_contains(strtolower($activity->action), 'updated') ? 'bg-purple-500' : '' }}
                                                {{ str_contains(strtolower($activity->action), 'suspended') ? 'bg-red-500' : '' }}
                                                {{ str_contains(strtolower($activity->action), 'activated') ? 'bg-green-500' : '' }}
                                                {{ !str_contains(strtolower($activity->action), 'login') && 
                                                   !str_contains(strtolower($activity->action), 'logout') && 
                                                   !str_contains(strtolower($activity->action), 'created') && 
                                                   !str_contains(strtolower($activity->action), 'updated') && 
                                                   !str_contains(strtolower($activity->action), 'suspended') && 
                                                   !str_contains(strtolower($activity->action), 'activated') ? 'bg-gray-500' : '' }}">
                                                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    @if(str_contains(strtolower($activity->action), 'login'))
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                                                    @elseif(str_contains(strtolower($activity->action), 'logout'))
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m-3 0 3-3m0 0-3-3m3 3H9" />
                                                    @elseif(str_contains(strtolower($activity->action), 'created'))
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                                                    @elseif(str_contains(strtolower($activity->action), 'updated'))
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    @elseif(str_contains(strtolower($activity->action), 'suspended'))
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                                    @elseif(str_contains(strtolower($activity->action), 'activated'))
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    @else
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    @endif
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div>
                                                <div class="text-sm">
                                                    <span class="font-medium text-gray-900">
                                                        {{ ucfirst(str_replace('_', ' ', $activity->action)) }}
                                                    </span>
                                                </div>
                                                <p class="mt-0.5 text-sm text-gray-500">
                                                    {{ $activity->created_at->format('M j, Y \a\t g:i A') }}
                                                    <span class="text-gray-400">({{ $activity->created_at->diffForHumans() }})</span>
                                                </p>
                                            </div>
                                            
                                            @if($activity->description)
                                            <div class="mt-2 text-sm text-gray-700">
                                                {{ $activity->description }}
                                            </div>
                                            @endif
                                            
                                            @if($activity->metadata)
                                            <div class="mt-2">
                                                <details class="text-sm">
                                                    <summary class="cursor-pointer text-blue-600 hover:text-blue-500">View details</summary>
                                                    <div class="mt-2 p-3 bg-gray-50 rounded-md">
                                                        <pre class="text-xs text-gray-600 whitespace-pre-wrap">{{ json_encode($activity->metadata, JSON_PRETTY_PRINT) }}</pre>
                                                    </div>
                                                </details>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No activities found</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            @if($actionFilter)
                                No activities matching "{{ $actionFilter }}" found for the selected time range.
                            @else
                                No activities recorded for this user in the selected time range.
                            @endif
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('activityManager', () => ({
        filters: {
            action_filter: @json($actionFilter ?? ''),
            days: {{ $days ?? 30 }}
        },
        loading: false,

        async applyFilters() {
            const params = new URLSearchParams();
            if (this.filters.action_filter) params.append('action_filter', this.filters.action_filter);
            if (this.filters.days) params.append('days', this.filters.days);
            
            const currentUrl = new URL(window.location);
            params.forEach((value, key) => currentUrl.searchParams.set(key, value));
            
            window.location.href = currentUrl.toString();
        },

        async refreshActivities() {
            this.loading = true;
            setTimeout(() => {
                window.location.reload();
            }, 500);
        }
    }))
})
</script>
@endsection