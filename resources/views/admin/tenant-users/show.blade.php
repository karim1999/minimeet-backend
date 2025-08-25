@extends('layouts.admin')

@section('content')
<div x-data="userStatusManager" class="space-y-6">
    <!-- Header -->
    <div class="border-b border-gray-200 pb-5">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="text-xl font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                </div>
                <div>
                    <h2 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">{{ $user->name }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="ml-2">{{ ucfirst($user->role) }}</span>
                        <span class="ml-2 text-gray-300">•</span>
                        <span class="ml-2">Tenant: {{ $tenant->id }}</span>
                    </p>
                </div>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-3">
                <button @click="toggleStatus" 
                        :class="user.is_active 
                            ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' 
                            : 'bg-green-600 hover:bg-green-700 focus:ring-green-500'"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                    <span x-text="user.is_active ? 'Disable User' : 'Enable User'"></span>
                </button>
                <a href="{{ route('admin.tenant-users.edit', ['id' => $tenant->id . ':' . $user->id]) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                    Edit User
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

    <!-- User Information Grid -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Contact Information</h3>
                    
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $user->email }}" class="text-blue-600 hover:text-blue-500">
                                    {{ $user->email }}
                                </a>
                            </dd>
                        </div>
                        
                        @if($user->phone)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:{{ $user->phone }}" class="text-blue-600 hover:text-blue-500">
                                    {{ $user->phone }}
                                </a>
                            </dd>
                        </div>
                        @endif
                        
                        @if($user->department)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Department</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->department }}</dd>
                        </div>
                        @endif
                        
                        @if($user->title)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Job Title</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->title }}</dd>
                        </div>
                        @endif
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Role</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $user->role === 'owner' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $user->role === 'admin' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $user->role === 'manager' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $user->role === 'member' ? 'bg-gray-100 text-gray-800' : '' }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                            <dd class="mt-1">
                                <span :class="user.is_active 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-red-100 text-red-800'"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                    <span x-text="user.is_active ? 'Active' : 'Inactive'"></span>
                                </span>
                            </dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tenant Information -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Tenant Information</h3>
                    
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tenant ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $tenant->id }}</dd>
                        </div>
                        
                        @if($tenant->domains->count() > 0)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Domains</dt>
                            <dd class="mt-1">
                                <div class="flex flex-wrap gap-2">
                                    @foreach($tenant->domains as $domain)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $domain->domain }}
                                        </span>
                                    @endforeach
                                </div>
                            </dd>
                        </div>
                        @endif
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tenant Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $tenant->created_at->format('M j, Y') }}</dd>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity & Stats Sidebar -->
        <div class="space-y-6">
            <!-- Account Details -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Account Details</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">User ID</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $user->id }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Account Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at->format('M j, Y \a\t g:i A') }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->updated_at->format('M j, Y \a\t g:i A') }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($user->last_login_at)
                                    {{ $user->last_login_at->format('M j, Y \a\t g:i A') }}
                                    <span class="text-xs text-gray-400">({{ $user->last_login_at->diffForHumans() }})</span>
                                @else
                                    <span class="text-gray-400">Never</span>
                                @endif
                            </dd>
                        </div>
                        
                        @if($user->email_verified_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Verified</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <div class="flex items-center">
                                    <svg class="h-4 w-4 text-green-500 mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {{ $user->email_verified_at->format('M j, Y') }}
                                </div>
                            </dd>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Quick Actions</h3>
                    
                    <div class="space-y-3">
                        <a href="{{ route('admin.tenant-users.activity', ['id' => $tenant->id . ':' . $user->id]) }}" 
                           class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            View Activity Log
                        </a>
                        
                        <a href="{{ route('admin.tenant-users.edit', ['id' => $tenant->id . ':' . $user->id]) }}" 
                           class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Edit User Details
                        </a>
                        
                        @if(auth()->user() && auth()->user()->isSuperAdmin())
                        <button @click="confirmDelete" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Delete User
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.173a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('userStatusManager', () => ({
        user: {
            id: {{ $user->id }},
            name: @json($user->name),
            email: @json($user->email),
            is_active: {{ $user->is_active ? 'true' : 'false' }},
            role: @json($user->role)
        },
        tenant: {
            id: @json($tenant->id)
        },

        async toggleStatus() {
            const action = this.user.is_active ? 'disable' : 'enable';
            if (confirm(`Are you sure you want to ${action} ${this.user.name}?`)) {
                try {
                    const response = await fetch(`/admin/tenant-users/${this.tenant.id}:${this.user.id}/toggle-status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        this.user.is_active = data.new_status;
                        
                        // Show success message
                        this.showToast(`User ${action}d successfully`, 'success');
                    } else {
                        throw new Error('Failed to update user status');
                    }
                } catch (error) {
                    console.error('Failed to toggle user status:', error);
                    this.showToast('Failed to update user status', 'error');
                }
            }
        },

        confirmDelete() {
            if (confirm(`Are you sure you want to delete ${this.user.name}? This action cannot be undone.`)) {
                if (confirm('This will permanently delete the user account and all associated data. Are you absolutely sure?')) {
                    this.deleteUser();
                }
            }
        },

        async deleteUser() {
            try {
                const response = await fetch(`/admin/tenant-users/${this.tenant.id}:${this.user.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    window.location.href = '/admin/tenant-users?deleted=' + encodeURIComponent(this.user.name);
                } else {
                    throw new Error('Failed to delete user');
                }
            } catch (error) {
                console.error('Failed to delete user:', error);
                this.showToast('Failed to delete user', 'error');
            }
        },

        showToast(message, type = 'info') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg ${
                type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' :
                type === 'error' ? 'bg-red-50 text-red-800 border border-red-200' :
                'bg-blue-50 text-blue-800 border border-blue-200'
            }`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
    }))
})
</script>
@endsection