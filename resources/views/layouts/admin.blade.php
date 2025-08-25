<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'MiniMeet') }} - Admin Dashboard</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="min-h-full">
        <!-- Navigation -->
        <nav class="bg-blue-600 shadow-lg">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-2xl font-bold text-white">MiniMeet Admin</h1>
                        </div>
                        <div class="hidden md:ml-10 md:flex md:space-x-8">
                            <a href="{{ route('admin.dashboard') }}" 
                               class="inline-flex items-center px-1 pt-1 text-sm font-medium text-white hover:text-blue-200 transition-colors duration-150 ease-in-out">
                                Dashboard
                            </a>
                            <a href="{{ route('admin.tenant-users.index') }}" 
                               class="inline-flex items-center px-1 pt-1 text-sm font-medium text-white hover:text-blue-200 transition-colors duration-150 ease-in-out">
                                Tenant Users
                            </a>
                            @if(auth()->user() && auth()->user()->isSuperAdmin())
                            <a href="{{ route('admin.system-stats') }}" 
                               class="inline-flex items-center px-1 pt-1 text-sm font-medium text-white hover:text-blue-200 transition-colors duration-150 ease-in-out">
                                System Stats
                            </a>
                            @endif
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <!-- User dropdown -->
                        <div class="relative ml-3" x-data="{ open: false }">
                            <div>
                                <button type="button" 
                                        @click="open = !open"
                                        class="relative flex max-w-xs items-center rounded-full bg-blue-600 text-sm focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-600" 
                                        id="user-menu-button" 
                                        aria-expanded="false" 
                                        aria-haspopup="true">
                                    <span class="sr-only">Open user menu</span>
                                    <div class="h-8 w-8 rounded-full bg-blue-800 flex items-center justify-center text-white font-medium">
                                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                                    </div>
                                </button>
                            </div>

                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 @click.outside="open = false"
                                 class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" 
                                 role="menu" 
                                 aria-orientation="vertical" 
                                 aria-labelledby="user-menu-button" 
                                 tabindex="-1">
                                <div class="px-4 py-2 text-sm text-gray-700 border-b">
                                    Signed in as <strong>{{ auth()->user()->name ?? 'Administrator' }}</strong>
                                </div>
                                <a href="#" 
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" 
                                   role="menuitem" 
                                   tabindex="-1">Sign out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main content -->
        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>

    <!-- Logout Form -->
    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
        @csrf
    </form>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>