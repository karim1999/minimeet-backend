@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    <!-- Header -->
    <x-admin.page-header 
        title="System Statistics"
        subtitle="Comprehensive system-wide statistics and performance metrics for MiniMeet platform."
    />

    <!-- Overview Stats -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Users -->
        <x-admin.stats-card 
            title="Total Platform Users"
            :value="$stats['total_users'] ?? 0"
            color="green"
            :trend="$stats['new_users_today'] ?? 0"
            trend-label="new today"
        />

        <!-- Active Users -->
        <x-admin.stats-card 
            title="Active Users"
            :value="$stats['active_users'] ?? 0"
            color="gray"
            :subtitle="round((($stats['active_users'] ?? 0) / max($stats['total_users'] ?? 1, 1)) * 100, 1) . '% activity rate'"
        />

        <!-- Total Tenants -->
        <x-admin.stats-card 
            title="Total Tenants"
            :value="$stats['total_tenants'] ?? 0"
            color="blue"
            :trend="$stats['active_tenants'] ?? 0"
            trend-label="active"
        />

        <!-- Recent Activities -->
        <x-admin.stats-card 
            title="Activities (24h)"
            :value="$stats['recent_activities'] ?? 0"
            color="indigo"
            trend-label="last 24 hours"
            :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z\' />'"
        />
    </div>

    <!-- Growth Stats -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
        <!-- Tenant Growth -->
        <x-admin.card header="Tenant Growth">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Total Tenants</span>
                    <span class="text-lg font-semibold">{{ $stats['tenants']['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Active Tenants</span>
                    <span class="text-lg font-semibold">{{ $stats['tenants']['active'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Monthly Growth</span>
                    <span class="text-lg font-semibold {{ ($stats['tenants']['growth'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($stats['tenants']['growth'] ?? 0, 1) }}%
                    </span>
                </div>
            </div>
        </x-admin.card>

        <!-- User Growth -->
        <x-admin.card header="User Growth">
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Total Users</span>
                    <span class="text-lg font-semibold">{{ $stats['users']['total'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Active Users</span>
                    <span class="text-lg font-semibold">{{ $stats['users']['active'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Monthly Growth</span>
                    <span class="text-lg font-semibold {{ ($stats['users']['growth'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($stats['users']['growth'] ?? 0, 1) }}%
                    </span>
                </div>
            </div>
        </x-admin.card>
    </div>

    <!-- Activity Stats -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Activity Statistics</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-3xl font-bold text-blue-600">{{ $stats['activities']['today'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">Today</div>
                </div>
                
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-3xl font-bold text-green-600">{{ $stats['activities']['this_week'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">This Week</div>
                </div>
                
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-3xl font-bold text-purple-600">{{ $stats['activities']['this_month'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">This Month</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tenant Statistics -->
    @if(!empty($tenantStats))
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Tenant Details</h3>
                <span class="text-sm text-gray-500">{{ $tenantStats->count() }} tenants</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Active</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($tenantStats->take(10) as $tenant)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $tenant['id'] }}</div>
                                    <div class="text-sm text-gray-500">
                                        @if(!empty($tenant['domains']))
                                            {{ implode(', ', $tenant['domains']) }}
                                        @else
                                            No domains configured
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $tenant['user_count'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $tenant['active_users'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($tenant['last_activity'])
                                    {{ \Carbon\Carbon::parse($tenant['last_activity'])->diffForHumans() }}
                                @else
                                    Never
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @switch($tenant['activity_status'])
                                        @case('active')
                                            bg-green-100 text-green-800
                                            @break
                                        @case('warning')
                                            bg-yellow-100 text-yellow-800
                                            @break
                                        @default
                                            bg-red-100 text-red-800
                                    @endswitch">
                                    {{ ucfirst($tenant['activity_status']) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            @if($tenantStats->count() > 10)
            <div class="mt-4 text-center">
                <p class="text-sm text-gray-500">Showing 10 of {{ $tenantStats->count() }} tenants</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- User Statistics -->
    @if(!empty($userStats))
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">User Distribution</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-3">User Summary</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Total Users:</span>
                            <span class="text-sm font-medium">{{ $userStats['total_users'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Active Users:</span>
                            <span class="text-sm font-medium">{{ $userStats['active_users'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Avg per Tenant:</span>
                            <span class="text-sm font-medium">{{ number_format($userStats['avg_users_per_tenant'] ?? 0, 1) }}</span>
                        </div>
                    </div>
                </div>
                
                @if(!empty($userStats['user_distribution']))
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-3">Tenant Size Distribution</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Small (≤10 users):</span>
                            <span class="text-sm font-medium">{{ $userStats['user_distribution']['small'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Medium (11-50 users):</span>
                            <span class="text-sm font-medium">{{ $userStats['user_distribution']['medium'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Large (>50 users):</span>
                            <span class="text-sm font-medium">{{ $userStats['user_distribution']['large'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- System Health -->
    @if(!empty($systemHealth))
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">System Health</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($systemHealth as $service => $health)
                <div class="text-center p-4 rounded-lg
                    @if(is_array($health) && ($health['status'] ?? '') === 'healthy')
                        bg-green-50 border border-green-200
                    @elseif(is_array($health) && ($health['status'] ?? '') === 'warning')
                        bg-yellow-50 border border-yellow-200
                    @elseif($health === 'healthy')
                        bg-green-50 border border-green-200
                    @else
                        bg-red-50 border border-red-200
                    @endif">
                    <div class="text-3xl font-bold mb-2
                        @if(is_array($health) && ($health['status'] ?? '') === 'healthy')
                            text-green-600
                        @elseif(is_array($health) && ($health['status'] ?? '') === 'warning')
                            text-yellow-600
                        @elseif($health === 'healthy')
                            text-green-600
                        @else
                            text-red-600
                        @endif">
                        @if((is_array($health) && ($health['status'] ?? '') === 'healthy') || $health === 'healthy')
                            ✓
                        @elseif(is_array($health) && ($health['status'] ?? '') === 'warning')
                            ⚠
                        @else
                            ✗
                        @endif
                    </div>
                    <div class="text-sm font-medium text-gray-900 capitalize">{{ $service }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ is_array($health) ? ($health['message'] ?? '') : ucfirst($health) }}
                    </div>
                    
                    @if(is_array($health) && isset($health['usage_percent']))
                    <div class="text-xs text-gray-600 mt-1">{{ $health['usage_percent'] }}% used</div>
                    @endif
                    
                    @if(is_array($health) && isset($health['free_space']))
                    <div class="text-xs text-gray-600 mt-1">{{ $health['free_space'] }} free</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- System Performance -->
    @if(!empty($stats['system']))
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">System Performance</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['system']['uptime'] ?? 0 }}%</div>
                    <div class="text-sm text-gray-500">System Uptime</div>
                </div>
                
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($stats['system']['memory_usage'] ?? 0, 1) }} MB</div>
                    <div class="text-sm text-gray-500">Memory Usage</div>
                </div>
                
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">{{ $stats['system']['database_size'] ?? 0 }} MB</div>
                    <div class="text-sm text-gray-500">Database Size</div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection