@extends('layouts.admin')

@section('content')
<div x-data="tenantUserManager" class="space-y-6">
    <!-- Header with Actions -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h2 class="text-3xl font-bold leading-tight tracking-tight text-gray-900">Tenant Users</h2>
            <p class="mt-2 max-w-4xl text-sm text-gray-500">
                Manage users across all tenants in your MiniMeet platform.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button type="button"
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
                Create User
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <input type="text" 
                       x-model="filters.search"
                       @input="applyFilters"
                       placeholder="Search users..."
                       class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
            </div>
            <div>
                <select x-model="filters.role"
                        @change="applyFilters"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="user">User</option>
                </select>
            </div>
            <div>
                <select x-model="filters.status"
                        @change="applyFilters"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                    <option value="">All Statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div>
                <select x-model="filters.tenant"
                        @change="applyFilters"
                        class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6">
                    <option value="">All Tenants</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tenant
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Last Login
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="user in users" :key="user.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span x-text="user.name.charAt(0).toUpperCase()" class="text-sm font-medium text-gray-700"></span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div x-text="user.name" class="text-sm font-medium text-gray-900"></div>
                                        <div x-text="user.email" class="text-sm text-gray-500"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div x-text="user.tenant?.name || 'Unknown'" class="text-sm text-gray-900"></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="{
                                    'bg-purple-100 text-purple-800': user.role === 'admin',
                                    'bg-blue-100 text-blue-800': user.role === 'manager',
                                    'bg-gray-100 text-gray-800': user.role === 'user'
                                }" 
                                class="inline-flex px-2 py-1 text-xs font-medium rounded-full">
                                    <span x-text="user.role.charAt(0).toUpperCase() + user.role.slice(1)"></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                      class="inline-flex px-2 py-1 text-xs font-medium rounded-full">
                                    <span x-text="user.is_active ? 'Active' : 'Inactive'"></span>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span x-text="user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <button @click="viewUser(user)" 
                                            class="text-blue-600 hover:text-blue-900">View</button>
                                    <button @click="editUser(user)" 
                                            class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                    <button @click="toggleUserStatus(user)" 
                                            :class="user.is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'">
                                        <span x-text="user.is_active ? 'Disable' : 'Enable'"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div x-show="pagination.total > pagination.per_page" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button @click="prevPage" 
                            :disabled="pagination.current_page <= 1"
                            :class="pagination.current_page <= 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white">
                        Previous
                    </button>
                    <button @click="nextPage"
                            :disabled="pagination.current_page >= pagination.last_page"
                            :class="pagination.current_page >= pagination.last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white">
                        Next
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span x-text="((pagination.current_page - 1) * pagination.per_page) + 1"></span> to 
                            <span x-text="Math.min(pagination.current_page * pagination.per_page, pagination.total)"></span> of 
                            <span x-text="pagination.total"></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <button @click="prevPage"
                                    :disabled="pagination.current_page <= 1"
                                    :class="pagination.current_page <= 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button @click="nextPage"
                                    :disabled="pagination.current_page >= pagination.last_page"
                                    :class="pagination.current_page >= pagination.last_page ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </nav>
                    </div>
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
    Alpine.data('tenantUserManager', () => ({
        users: {!! json_encode($users ?? []) !!},
        pagination: {!! json_encode($pagination ?? ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 15]) !!},
        filters: {
            search: '',
            role: '',
            status: '',
            tenant: ''
        },
        loading: false,
        showCreateModal: false,

        async applyFilters() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) params.append(key, value);
                });

                const response = await fetch(`/admin/tenant-users?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.users = data.users || [];
                    this.pagination = data.pagination || this.pagination;
                }
            } catch (error) {
                console.error('Failed to load users:', error);
            } finally {
                this.loading = false;
            }
        },

        async toggleUserStatus(user) {
            if (confirm(`Are you sure you want to ${user.is_active ? 'disable' : 'enable'} ${user.name}?`)) {
                try {
                    const response = await fetch(`/admin/tenant-users/${user.id}/toggle-status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        const userIndex = this.users.findIndex(u => u.id === user.id);
                        if (userIndex !== -1) {
                            this.users[userIndex] = data.user;
                        }
                    }
                } catch (error) {
                    console.error('Failed to toggle user status:', error);
                    alert('Failed to update user status');
                }
            }
        },

        viewUser(user) {
            window.location.href = `/admin/tenant-users/${user.id}`;
        },

        editUser(user) {
            window.location.href = `/admin/tenant-users/${user.id}/edit`;
        },

        async prevPage() {
            if (this.pagination.current_page > 1) {
                await this.loadPage(this.pagination.current_page - 1);
            }
        },

        async nextPage() {
            if (this.pagination.current_page < this.pagination.last_page) {
                await this.loadPage(this.pagination.current_page + 1);
            }
        },

        async loadPage(page) {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                params.append('page', page);
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) params.append(key, value);
                });

                const response = await fetch(`/admin/tenant-users?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.users = data.users || [];
                    this.pagination = data.pagination || this.pagination;
                }
            } catch (error) {
                console.error('Failed to load page:', error);
            } finally {
                this.loading = false;
            }
        }
    }))
})
</script>
@endsection