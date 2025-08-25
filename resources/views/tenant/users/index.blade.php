<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>User Management - {{ config('app.name', 'MiniMeet') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div id="app" x-data="userManagement" class="min-h-full">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <a href="/" class="text-2xl font-bold text-gray-900">MiniMeet</a>
                        <nav class="ml-10 flex space-x-8">
                            <a href="/" class="text-gray-700 hover:text-blue-600">Dashboard</a>
                            <a href="/users" class="text-blue-600 font-medium">Users</a>
                            <a href="/meetings" class="text-gray-700 hover:text-blue-600">Meetings</a>
                        </nav>
                    </div>
                    <div class="flex items-center gap-4">
                        <span x-text="`Welcome, ${currentUser?.name}`" class="text-sm text-gray-700"></span>
                        <button @click="logout" class="text-sm text-red-600 hover:text-red-800">
                            Sign Out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <!-- Page Header -->
                <div class="md:flex md:items-center md:justify-between">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                            User Management
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Manage users in your organization
                        </p>
                    </div>
                    <div x-show="currentUser?.role === 'admin'" class="mt-4 flex md:ml-4 md:mt-0">
                        <button @click="showCreateModal = true"
                                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                            <svg class="-ml-0.5 mr-1.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                            Add User
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-4">
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
                        <div class="flex items-center">
                            <button @click="refreshUsers" 
                                    class="inline-flex items-center rounded-md bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600">
                                <svg class="-ml-0.5 mr-1.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                                </svg>
                                Refresh
                            </button>
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
                                        Role
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Last Login
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
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
                                                <button @click="viewProfile(user)" 
                                                        class="text-blue-600 hover:text-blue-900">View</button>
                                                <template x-if="currentUser?.role === 'admin' && user.id !== currentUser.id">
                                                    <button @click="editUser(user)" 
                                                            class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                                </template>
                                                <template x-if="currentUser?.role === 'admin' && user.id !== currentUser.id">
                                                    <button @click="toggleUserStatus(user)" 
                                                            :class="user.is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'">
                                                        <span x-text="user.is_active ? 'Disable' : 'Enable'"></span>
                                                    </button>
                                                </template>
                                                <template x-if="user.id === currentUser?.id">
                                                    <button @click="editProfile()" 
                                                            class="text-green-600 hover:text-green-900">Edit Profile</button>
                                                </template>
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
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <button @click="prevPage"
                                                :disabled="pagination.current_page <= 1"
                                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <button @click="nextPage"
                                                :disabled="pagination.current_page >= pagination.last_page"
                                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
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
        </main>

        <!-- Create User Modal -->
        <div x-show="showCreateModal" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="showCreateModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.outside="showCreateModal = false"
                     class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    
                    <form @submit.prevent="submitCreateUser" class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Add New User</h3>
                            <p class="text-sm text-gray-600">Create a new user account for your organization</p>
                        </div>

                        <div x-show="createError" class="rounded-md bg-red-50 p-4">
                            <p x-text="createError" class="text-sm text-red-700"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input x-model="createForm.name" 
                                   type="text" 
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input x-model="createForm.email" 
                                   type="email" 
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            <input x-model="createForm.password" 
                                   type="password" 
                                   required 
                                   minlength="8"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Role</label>
                            <select x-model="createForm.role" 
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="user">User</option>
                            </select>
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit" 
                                    :disabled="createLoading"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:col-start-2">
                                <span x-show="!createLoading">Create User</span>
                                <span x-show="createLoading">Creating...</span>
                            </button>
                            <button type="button" 
                                    @click="showCreateModal = false"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- User Profile Modal -->
        <div x-show="showProfileModal" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="showProfileModal"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.outside="showProfileModal = false"
                     class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">User Profile</h3>
                        </div>

                        <template x-if="selectedUser">
                            <div class="space-y-4">
                                <div class="flex items-center space-x-4">
                                    <div class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center">
                                        <span x-text="selectedUser.name.charAt(0).toUpperCase()" class="text-xl font-medium text-gray-700"></span>
                                    </div>
                                    <div>
                                        <h4 x-text="selectedUser.name" class="text-lg font-medium text-gray-900"></h4>
                                        <p x-text="selectedUser.email" class="text-sm text-gray-500"></p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Role</dt>
                                        <dd x-text="selectedUser.role.charAt(0).toUpperCase() + selectedUser.role.slice(1)" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd>
                                            <span :class="selectedUser.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                                  class="inline-flex px-2 py-1 text-xs font-medium rounded-full">
                                                <span x-text="selectedUser.is_active ? 'Active' : 'Inactive'"></span>
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                                        <dd x-text="selectedUser.last_login_at ? new Date(selectedUser.last_login_at).toLocaleDateString() : 'Never'" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Member Since</dt>
                                        <dd x-text="new Date(selectedUser.created_at).toLocaleDateString()" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                </div>

                                <template x-if="selectedUser.bio">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Bio</dt>
                                        <dd x-text="selectedUser.bio" class="mt-1 text-sm text-gray-900"></dd>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <div class="mt-5 sm:mt-6">
                            <button type="button" 
                                    @click="showProfileModal = false"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('userManagement', () => ({
                users: [],
                currentUser: null,
                selectedUser: null,
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    total: 0,
                    per_page: 15
                },
                filters: {
                    search: '',
                    role: '',
                    status: ''
                },
                loading: false,
                showCreateModal: false,
                showProfileModal: false,
                createForm: {
                    name: '',
                    email: '',
                    password: '',
                    role: ''
                },
                createLoading: false,
                createError: '',

                async init() {
                    await this.checkAuth();
                    await this.loadUsers();
                },

                async checkAuth() {
                    const token = localStorage.getItem('tenant_token');
                    if (!token) {
                        window.location.href = '/';
                        return;
                    }

                    try {
                        const response = await fetch('/auth/user', {
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            this.currentUser = data.data.user;
                        } else {
                            localStorage.removeItem('tenant_token');
                            window.location.href = '/';
                        }
                    } catch (error) {
                        console.error('Auth check failed:', error);
                        localStorage.removeItem('tenant_token');
                        window.location.href = '/';
                    }
                },

                async loadUsers() {
                    this.loading = true;
                    try {
                        const token = localStorage.getItem('tenant_token');
                        const params = new URLSearchParams();
                        Object.entries(this.filters).forEach(([key, value]) => {
                            if (value) params.append(key, value);
                        });

                        const response = await fetch(`/users?${params}`, {
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            this.users = data.data.users || [];
                            this.pagination = data.data.pagination || this.pagination;
                        }
                    } catch (error) {
                        console.error('Failed to load users:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async applyFilters() {
                    this.pagination.current_page = 1;
                    await this.loadUsers();
                },

                async refreshUsers() {
                    await this.loadUsers();
                },

                async prevPage() {
                    if (this.pagination.current_page > 1) {
                        this.pagination.current_page--;
                        await this.loadUsers();
                    }
                },

                async nextPage() {
                    if (this.pagination.current_page < this.pagination.last_page) {
                        this.pagination.current_page++;
                        await this.loadUsers();
                    }
                },

                viewProfile(user) {
                    this.selectedUser = user;
                    this.showProfileModal = true;
                },

                editUser(user) {
                    // Implementation for editing user
                    alert('Edit user functionality to be implemented');
                },

                editProfile() {
                    // Implementation for editing own profile
                    alert('Edit profile functionality to be implemented');
                },

                async toggleUserStatus(user) {
                    if (confirm(`Are you sure you want to ${user.is_active ? 'disable' : 'enable'} ${user.name}?`)) {
                        try {
                            const token = localStorage.getItem('tenant_token');
                            const response = await fetch(`/users/${user.id}/toggle-status`, {
                                method: 'POST',
                                headers: {
                                    'Authorization': `Bearer ${token}`,
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });

                            if (response.ok) {
                                await this.loadUsers();
                            }
                        } catch (error) {
                            console.error('Failed to toggle user status:', error);
                            alert('Failed to update user status');
                        }
                    }
                },

                async submitCreateUser() {
                    this.createLoading = true;
                    this.createError = '';

                    try {
                        const token = localStorage.getItem('tenant_token');
                        const response = await fetch('/users', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${token}`,
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.createForm)
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.showCreateModal = false;
                            this.createForm = { name: '', email: '', password: '', role: '' };
                            await this.loadUsers();
                        } else {
                            this.createError = data.message || 'Failed to create user';
                        }
                    } catch (error) {
                        console.error('Create user error:', error);
                        this.createError = 'Network error. Please try again.';
                    }

                    this.createLoading = false;
                },

                async logout() {
                    try {
                        const token = localStorage.getItem('tenant_token');
                        if (token) {
                            await fetch('/auth/logout', {
                                method: 'POST',
                                headers: {
                                    'Authorization': `Bearer ${token}`,
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Logout error:', error);
                    }

                    localStorage.removeItem('tenant_token');
                    window.location.href = '/';
                }
            }));
        });
    </script>
</body>
</html>