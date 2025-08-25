@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    <!-- Header -->
    <div class="border-b border-gray-200 pb-5">
        <h2 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">Admin Dashboard</h2>
        <p class="mt-2 max-w-4xl text-sm text-gray-500">
            Manage your MiniMeet tenant users and system overview.
        </p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Users -->
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Total Tenant Users</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $stats['total_users'] ?? 0 }}</dd>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-green-600">
                    <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25 12 21m0 0-3.75-3.75M12 21V3" />
                    </svg>
                </span>
                <span class="font-medium text-green-600">{{ $stats['new_users_today'] ?? 0 }}</span>
                <span class="ml-1 text-gray-500">new today</span>
            </div>
        </div>

        <!-- Active Users -->
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Active Users</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $stats['active_users'] ?? 0 }}</dd>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-gray-600">
                    {{ round((($stats['active_users'] ?? 0) / max($stats['total_users'] ?? 1, 1)) * 100, 1) }}%
                </span>
                <span class="ml-1 text-gray-500">of total</span>
            </div>
        </div>

        <!-- Total Tenants -->
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Total Tenants</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $stats['total_tenants'] ?? 0 }}</dd>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-blue-600">
                    <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15l-.75 18H5.25L4.5 3Z" />
                    </svg>
                </span>
                <span class="font-medium text-blue-600">{{ $stats['active_tenants'] ?? 0 }}</span>
                <span class="ml-1 text-gray-500">active</span>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt class="truncate text-sm font-medium text-gray-500">Activities (24h)</dt>
            <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $stats['recent_activities'] ?? 0 }}</dd>
            <div class="mt-2 flex items-center text-sm">
                <span class="text-indigo-600">
                    <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <span class="ml-1 text-gray-500">last 24 hours</span>
            </div>
        </div>
    </div>

    <!-- Recent Users & Quick Actions -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
        <!-- Recent Users -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Users</h3>
                    <a href="{{ route('admin.tenant-users.index') }}" 
                       class="text-sm font-medium text-blue-600 hover:text-blue-500">
                        View all →
                    </a>
                </div>
                
                @if(!empty($recentUsers))
                    <div class="flow-root">
                        <ul role="list" class="-my-3">
                            @foreach($recentUsers as $user)
                            <li class="py-3">
                                <div class="flex items-center space-x-3">
                                    <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            {{ strtoupper(substr($user['name'], 0, 1)) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $user['name'] }}</p>
                                        <p class="text-sm text-gray-500 truncate">{{ $user['email'] }}</p>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($user['is_active'])
                                                bg-green-100 text-green-800
                                            @else
                                                bg-red-100 text-red-800
                                            @endif">
                                            {{ $user['is_active'] ? 'Active' : 'Inactive' }}
                                        </span>
                                        <span class="text-xs text-gray-400 mt-1">{{ $user['role'] }}</span>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="text-sm text-gray-500">No recent users found.</p>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                
                <div class="space-y-4">
                    <a href="{{ route('admin.tenant-users.create') }}" 
                       class="w-full flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        Create New User
                    </a>
                    
                    <a href="{{ route('admin.tenant-users.index') }}?status=inactive" 
                       class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        View Inactive Users
                    </a>
                    
                    <a href="{{ route('admin.system-stats') }}" 
                       class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 1 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        System Statistics
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($systemHealth))
    <!-- System Health -->
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">System Health</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold 
                        @if($systemHealth['database'] === 'healthy')
                            text-green-600
                        @else
                            text-red-600
                        @endif">
                        @if($systemHealth['database'] === 'healthy')
                            ✓
                        @else
                            ✗
                        @endif
                    </div>
                    <div class="text-sm text-gray-500">Database</div>
                </div>
                
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold 
                        @if($systemHealth['cache'] === 'healthy')
                            text-green-600
                        @else
                            text-red-600
                        @endif">
                        @if($systemHealth['cache'] === 'healthy')
                            ✓
                        @else
                            ✗
                        @endif
                    </div>
                    <div class="text-sm text-gray-500">Cache</div>
                </div>
                
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold 
                        @if($systemHealth['queue'] === 'healthy')
                            text-green-600
                        @else
                            text-red-600
                        @endif">
                        @if($systemHealth['queue'] === 'healthy')
                            ✓
                        @else
                            ✗
                        @endif
                    </div>
                    <div class="text-sm text-gray-500">Queue</div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection