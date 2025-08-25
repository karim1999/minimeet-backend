<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'MiniMeet') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gradient-to-br from-blue-50 to-indigo-100">
    <div id="app" x-data="tenantAuth" class="min-h-full">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-gray-900">MiniMeet</h1>
                        <span class="ml-3 text-sm text-gray-500">{{ tenant('id') ? 'Organization Portal' : 'Platform' }}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <template x-if="!isAuthenticated">
                            <div class="flex items-center gap-2">
                                <button @click="showLogin = true; showRegister = false" 
                                        :class="showLogin ? 'bg-blue-600 text-white' : 'text-gray-700 hover:text-blue-600'"
                                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-150 ease-in-out">
                                    Sign In
                                </button>
                                <button @click="showRegister = true; showLogin = false"
                                        :class="showRegister ? 'bg-blue-600 text-white' : 'text-gray-700 hover:text-blue-600 border border-gray-300'"
                                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors duration-150 ease-in-out">
                                    Register
                                </button>
                            </div>
                        </template>
                        <template x-if="isAuthenticated">
                            <div class="flex items-center gap-4">
                                <span x-text="`Welcome, ${user?.name}`" class="text-sm text-gray-700"></span>
                                <button @click="logout" class="text-sm text-red-600 hover:text-red-800">
                                    Sign Out
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <!-- Welcome Section (when not authenticated) -->
            <template x-if="!isAuthenticated">
                <div class="text-center">
                    <div class="mx-auto max-w-2xl">
                        <h2 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl">
                            Welcome to MiniMeet
                        </h2>
                        <p class="mt-6 text-lg leading-8 text-gray-600">
                            Optimize your meeting efficiency and reduce costs with AI-powered insights and recommendations for your organization.
                        </p>
                        <div class="mt-10 flex items-center justify-center gap-x-6">
                            <button @click="showRegister = true; showLogin = false" 
                                    class="rounded-md bg-blue-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                                Get Started
                            </button>
                            <button @click="showLogin = true; showRegister = false"
                                    class="text-sm font-semibold leading-6 text-gray-900">
                                Sign in <span aria-hidden="true">→</span>
                            </button>
                        </div>
                    </div>

                    <!-- Features Section -->
                    <div class="mt-20">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="bg-white p-6 rounded-lg shadow-sm">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Meeting Analytics</h3>
                                <p class="text-gray-600">Track and analyze meeting efficiency with detailed insights and recommendations.</p>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow-sm">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cost Optimization</h3>
                                <p class="text-gray-600">Identify and reduce meeting costs with AI-powered optimization suggestions.</p>
                            </div>

                            <div class="bg-white p-6 rounded-lg shadow-sm">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Real-time Insights</h3>
                                <p class="text-gray-600">Get instant feedback and recommendations to improve meeting productivity.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Dashboard (when authenticated) -->
            <template x-if="isAuthenticated">
                <div class="space-y-8">
                    <div class="text-center">
                        <h2 class="text-3xl font-bold text-gray-900">Welcome back!</h2>
                        <p class="mt-2 text-gray-600">Here's what's happening with your meetings today.</p>
                    </div>

                    <!-- Quick Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-2xl font-semibold text-gray-900">12</p>
                                    <p class="text-sm text-gray-600">Meetings Today</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <div class="p-2 bg-green-100 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-2xl font-semibold text-gray-900">2.5h</p>
                                    <p class="text-sm text-gray-600">Time Saved</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <div class="p-2 bg-purple-100 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-2xl font-semibold text-gray-900">85</p>
                                    <p class="text-sm text-gray-600">Participants</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-lg shadow-sm">
                            <div class="flex items-center">
                                <div class="p-2 bg-yellow-100 rounded-lg">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-2xl font-semibold text-gray-900">92%</p>
                                    <p class="text-sm text-gray-600">Efficiency Score</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Links -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <a href="/users" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-blue-100 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">User Management</h3>
                                    <p class="text-sm text-gray-600">Manage organization users and permissions</p>
                                </div>
                            </div>
                        </a>

                        <a href="/meetings" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-green-100 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Meetings</h3>
                                    <p class="text-sm text-gray-600">View and analyze meeting data</p>
                                </div>
                            </div>
                        </a>

                        <a href="/insights" class="block p-6 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                            <div class="flex items-center">
                                <div class="p-3 bg-purple-100 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">AI Insights</h3>
                                    <p class="text-sm text-gray-600">Get recommendations and analytics</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </template>
        </main>

        <!-- Login Modal -->
        <div x-show="showLogin" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-10 overflow-y-auto" 
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="showLogin"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.outside="showLogin = false"
                     class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                    
                    <form @submit.prevent="submitLogin" class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Sign In</h3>
                            <p class="text-sm text-gray-600">Access your organization's MiniMeet portal</p>
                        </div>

                        <div x-show="loginError" class="rounded-md bg-red-50 p-4">
                            <p x-text="loginError" class="text-sm text-red-700"></p>
                        </div>

                        <div>
                            <label for="login-email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input x-model="loginForm.email" 
                                   type="email" 
                                   id="login-email"
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="login-password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input x-model="loginForm.password" 
                                   type="password" 
                                   id="login-password"
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div class="flex items-center">
                            <input x-model="loginForm.remember" 
                                   type="checkbox" 
                                   id="remember"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-900">Remember me</label>
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit" 
                                    :disabled="loginLoading"
                                    :class="loginLoading ? 'opacity-50 cursor-not-allowed' : ''"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 sm:col-start-2">
                                <span x-show="!loginLoading">Sign In</span>
                                <span x-show="loginLoading">Signing in...</span>
                            </button>
                            <button type="button" 
                                    @click="showLogin = false"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Register Modal -->
        <div x-show="showRegister" 
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-10 overflow-y-auto" 
             aria-labelledby="modal-title" 
             role="dialog" 
             aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div x-show="showRegister"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.outside="showRegister = false"
                     class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-6">
                    
                    <form @submit.prevent="submitRegister" class="space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Create Account</h3>
                            <p class="text-sm text-gray-600">Join your organization's MiniMeet portal</p>
                        </div>

                        <div x-show="registerError" class="rounded-md bg-red-50 p-4">
                            <p x-text="registerError" class="text-sm text-red-700"></p>
                        </div>

                        <div>
                            <label for="register-name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input x-model="registerForm.name" 
                                   type="text" 
                                   id="register-name"
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="register-email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input x-model="registerForm.email" 
                                   type="email" 
                                   id="register-email"
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="register-password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input x-model="registerForm.password" 
                                   type="password" 
                                   id="register-password"
                                   required 
                                   minlength="8"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="register-password-confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input x-model="registerForm.password_confirmation" 
                                   type="password" 
                                   id="register-password-confirmation"
                                   required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                            <button type="submit" 
                                    :disabled="registerLoading"
                                    :class="registerLoading ? 'opacity-50 cursor-not-allowed' : ''"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 sm:col-start-2">
                                <span x-show="!registerLoading">Create Account</span>
                                <span x-show="registerLoading">Creating...</span>
                            </button>
                            <button type="button" 
                                    @click="showRegister = false"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tenantAuth', () => ({
                showLogin: false,
                showRegister: false,
                isAuthenticated: false,
                user: null,
                loginForm: {
                    email: '',
                    password: '',
                    remember: false
                },
                registerForm: {
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: ''
                },
                loginLoading: false,
                registerLoading: false,
                loginError: '',
                registerError: '',

                init() {
                    // Check if user is already authenticated
                    this.checkAuth();
                },

                async checkAuth() {
                    const token = localStorage.getItem('tenant_token');
                    if (token) {
                        try {
                            const response = await fetch('/auth/user', {
                                headers: {
                                    'Authorization': `Bearer ${token}`,
                                    'Accept': 'application/json'
                                }
                            });

                            if (response.ok) {
                                const data = await response.json();
                                this.user = data.user;
                                this.isAuthenticated = true;
                            } else {
                                localStorage.removeItem('tenant_token');
                            }
                        } catch (error) {
                            console.error('Auth check failed:', error);
                            localStorage.removeItem('tenant_token');
                        }
                    }
                },

                async submitLogin() {
                    this.loginLoading = true;
                    this.loginError = '';

                    try {
                        const response = await fetch('/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.loginForm)
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            localStorage.setItem('tenant_token', data.data.token);
                            this.user = data.data.user;
                            this.isAuthenticated = true;
                            this.showLogin = false;
                            this.loginForm = { email: '', password: '', remember: false };
                        } else {
                            this.loginError = data.message || 'Login failed';
                        }
                    } catch (error) {
                        console.error('Login error:', error);
                        this.loginError = 'Network error. Please try again.';
                    }

                    this.loginLoading = false;
                },

                async submitRegister() {
                    this.registerLoading = true;
                    this.registerError = '';

                    try {
                        const response = await fetch('/auth/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.registerForm)
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            localStorage.setItem('tenant_token', data.data.token);
                            this.user = data.data.user;
                            this.isAuthenticated = true;
                            this.showRegister = false;
                            this.registerForm = { name: '', email: '', password: '', password_confirmation: '' };
                        } else {
                            this.registerError = data.message || 'Registration failed';
                        }
                    } catch (error) {
                        console.error('Register error:', error);
                        this.registerError = 'Network error. Please try again.';
                    }

                    this.registerLoading = false;
                },

                async logout() {
                    try {
                        const token = localStorage.getItem('tenant_token');
                        if (token) {
                            await fetch('/auth/logout', {
                                method: 'POST',
                                headers: {
                                    'Authorization': `Bearer ${token}`,
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Logout error:', error);
                    }

                    localStorage.removeItem('tenant_token');
                    this.user = null;
                    this.isAuthenticated = false;
                }
            }));
        });
    </script>
</body>
</html>