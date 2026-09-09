{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vumbi AI - @yield('title', 'Admin Dashboard')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="antialiased bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 selection:bg-emerald-500 selection:text-white transition-colors duration-200 min-h-full">
    <!-- Ambient Backdrop Accent Blurs -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-24 -left-20 w-96 h-96 bg-emerald-400/20 dark:bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-20 w-96 h-96 bg-sky-300/20 dark:bg-sky-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="min-h-screen relative z-10 flex flex-col">
        <!-- Main Adaptive Glass Top Bar (Admin Scope) -->
        <nav class="sticky top-0 z-40 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Brand Identifier -->
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 tracking-tight">Vumbi</span>
                            <span class="text-xl font-bold text-slate-800 dark:text-white">AI</span>
                        </a>
                        <span class="ml-3 px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60 rounded-full shadow-2xs">
                            Console
                        </span>
                    </div>

                    <!-- Right Controls (Brand Selector + User Dropdown) -->
                    <div class="flex items-center space-x-4">
                        @auth
                            @livewire('brand-selector')

                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                    class="flex items-center space-x-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:text-emerald-600 dark:hover:text-emerald-400 focus:outline-hidden transition-colors duration-150">
                                    <span>{{ auth()->user()->name }}</span>
                                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown Container with Glass Overlay -->
                                <div x-show="open" 
                                    @click.away="open = false"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-52 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl rounded-xl shadow-xl border border-slate-200/80 dark:border-slate-800 z-50 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                                    <div class="py-1">
                                        <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/50 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                                            Dashboard
                                        </a>
                                        <a href="{{ route('brands.index') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/50 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                                            Brand Settings
                                        </a>
                                        <a href="{{ route('system.status') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/50 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                                            System Status
                                        </a>
                                    </div>
                                    <div class="py-1">
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50/60 dark:hover:bg-rose-950/40 transition-colors">
                                                Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar + Main Content Frame -->
        <div class="flex flex-1 overflow-hidden">
            @auth
                <!-- Adaptive Glass Sidebar Navigation -->
                <x-aside />
            @endauth

            <!-- Main Page View -->
            <main class="flex-1 min-h-[calc(100vh-4rem)] p-6">
                @yield('content')
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>

</html>