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
        <div class="flex flex-1">
            @auth
                <!-- Adaptive Glass Sidebar Navigation -->
                <aside class="w-64 bg-white/60 dark:bg-slate-900/60 backdrop-blur-md border-r border-slate-200/60 dark:border-slate-800/60 sticky top-16 h-[calc(100vh-4rem)] overflow-y-auto transition-colors duration-200">
                    <nav class="p-4 space-y-1">
                        <!-- Section: Core -->
                        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2">
                            Core
                        </div>

                        <a href="{{ route('dashboard') }}"
                            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Dashboard</span>
                        </a>

                        <!-- Section: Analytics & Intelligence -->
                        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
                            Analytics & Intelligence
                        </div>

                        <a href="{{ route('analytics.index') }}"
                            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('analytics.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('analytics.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <span>Analytics</span>
                            <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">Live</span>
                        </a>

                        <a href="{{ route('briefs.index') }}"
                            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('briefs.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('briefs.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>AI Brief</span>
                            <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 border border-sky-200/50 dark:border-sky-800/50">Daily</span>
                        </a>

                        <a href="{{ route('seo.index') }}"
                            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('seo.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('seo.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span>SEO Assistant</span>
                            <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200/50 dark:border-amber-800/50">Monitor</span>
                        </a>

                        <!-- Section: Workflow -->
                        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
                            Workflow
                        </div>

                        <a href="{{ route('actions.queue') }}"
                            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('actions.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('actions.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span>Action Queue</span>
                            <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200/50 dark:border-amber-800/50" id="pending-count">0</span>
                        </a>

                        <a href="{{ route('blog.index') }}"
                            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('blog.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('blog.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v14m0 0V6m0 14H5m14 0h3m-3 0h-3M5 10h10M5 14h6m-6 4h10" />
                            </svg>
                            <span>Blog</span>
                            <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">Content</span>
                        </a>

                        <!-- AI Provider Footer Box -->
                        <div class="mt-6 p-3 bg-white/40 dark:bg-slate-800/40 backdrop-blur-xs rounded-xl border border-slate-200/60 dark:border-slate-800">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500 dark:text-slate-400">AI Provider</span>
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ config('ai.provider', 'ollama') }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs mt-1.5">
                                <span class="text-slate-500 dark:text-slate-400">Status</span>
                                <span class="inline-flex items-center font-medium text-emerald-600 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                    Connected
                                </span>
                            </div>
                        </div>
                    </nav>
                </aside>
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