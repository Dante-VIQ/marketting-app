<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vumbi AI - @yield('title', 'Dashboard')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="antialiased bg-gray-50">
    <div class="min-h-screen">
        <!-- Top Navigation -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-green-600">Vumbi</span>
                            <span class="text-xl font-bold text-gray-700">AI</span>
                        </a>
                        <span class="ml-3 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Ecosystem</span>
                    </div>

                    <div class="flex items-center space-x-4">
                        @auth
                            @livewire('brand-selector')

                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                    class="flex items-center space-x-2 text-sm text-gray-700 hover:text-gray-900 focus:outline-none">
                                    <span>{{ auth()->user()->name }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="open" @click.away="open = false"
                                    class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                    <div class="py-1">
                                        <a href="{{ route('dashboard') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Dashboard
                                        </a>
                                        <a href="{{ route('brands.index') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            Brand Settings
                                        </a>
                                        <a href="{{ route('system.status') }}"
                                            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            System Status
                                        </a>
                                        <div class="border-t border-gray-200"></div>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit"
                                                class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
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

        <!-- Sidebar + Main Content -->
        <div class="flex">
            <!-- Sidebar Navigation -->
            @auth
                <aside class="w-64 min-h-screen bg-white border-r border-gray-200 overflow-y-auto">
                    <nav class="p-4 space-y-1">
                        <!-- Section: Core -->
                        <div
                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-2 nav-section-title">
                            Core
                        </div>

                        <!-- Dashboard -->
                        <a href="{{ route('dashboard') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('dashboard') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="nav-link-text">Dashboard</span>
                        </a>

                        <!-- Section: Analytics & Intelligence -->
                        <div
                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-2 mt-4 nav-section-title">
                            Analytics & Intelligence
                        </div>

                        <!-- Analytics -->
                        <a href="{{ route('analytics.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('analytics.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <span class="nav-link-text">Analytics</span>
                            <span class="nav-link-badge ml-auto text-xs text-green-600">Live</span>
                        </a>

                        <!-- AI Brief -->
                        <a href="{{ route('briefs.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('briefs.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span class="nav-link-text">AI Brief</span>
                            <span class="nav-link-badge ml-auto text-xs text-blue-600">Daily</span>
                        </a>

                        <!-- SEO Assistant -->
                        <a href="{{ route('seo.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('seo.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span class="nav-link-text">SEO Assistant</span>
                            <span class="nav-link-badge ml-auto text-xs text-orange-600">Monitor</span>
                        </a>

                        <!-- Ahrefs -->
<a href="{{ route('ahrefs.index') }}" 
   class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('ahrefs.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
    <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
    </svg>
    <span class="nav-link-text">Ahrefs SEO</span>
    <span class="nav-link-badge ml-auto text-xs text-purple-600">Advanced</span>
</a>
                        <!-- Section: Workflow -->
                        <div
                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-2 mt-4 nav-section-title">
                            Workflow
                        </div>

                        <!-- Action Queue -->
                        <a href="{{ route('actions.queue') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('actions.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <span class="nav-link-text">Action Queue</span>
                            <span class="nav-link-badge ml-auto text-xs text-yellow-600" id="pending-count">0</span>
                        </a>

                        <!-- Blog -->
                        <a href="{{ route('blog.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('blog.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v14m0 0V6m0 14H5m14 0h3m-3 0h-3M5 10h10M5 14h6m-6 4h10" />
                            </svg>
                            <span class="nav-link-text">Blog</span>
                            <span class="nav-link-badge ml-auto text-xs text-green-600">Content</span>
                        </a>

                        <!-- Content Drafts -->
                        <a href="{{ route('content.drafts') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('content.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span class="nav-link-text">Content Drafts</span>
                            <span class="nav-link-badge ml-auto text-xs text-blue-600">Review</span>
                        </a>

                        <!-- Page Scanner -->
                        <a href="{{ route('scanner.index') }}"
                        class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('scanner.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span class="nav-link-text">Page Scanner</span>
                        <span class="nav-link-badge ml-auto text-xs text-purple-600">AI Context</span>
                        </a>

                        <!-- Section: Business -->
                        <div
                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-2 mt-4 nav-section-title">
                            Business
                        </div>

                        <!-- Campaigns -->
                        <a href="{{ route('campaigns.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('campaigns.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20.488 9H15V3.512A9.001 9.001 0 0120.488 9z" />
                            </svg>
                            <span class="nav-link-text">Campaigns</span>
                            <span class="nav-link-badge ml-auto text-xs text-green-600">ROI</span>
                        </a>

                        <!-- Affiliate -->
                        <a href="{{ route('affiliate.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('affiliate.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="nav-link-text">Affiliate</span>
                            <span class="nav-link-badge ml-auto text-xs text-yellow-600">Revenue</span>
                        </a>
                        <!-- Leads -->
                        <a href="{{ route('leads.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('leads.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="nav-link-text">Leads</span>
                            <span class="nav-link-badge ml-auto text-xs text-red-500" id="hot-leads-count">0</span>
                        </a>

                        <!-- Section: AI Ecosystem -->
                        <div
                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-2 mt-4 nav-section-title">
                            AI Ecosystem
                        </div>

                        <!-- Marketing AI -->
                        <a href="{{ route('marketing.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('marketing.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span class="nav-link-text">Marketing AI</span>
                            <span class="nav-link-badge ml-auto text-xs text-green-600">Active</span>
                        </a>

                        <!-- Healthcare AI (Nafasi) - Coming Soon -->
                        <a href="#"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-50 cursor-not-allowed opacity-60">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span class="nav-link-text">Healthcare AI (Nafasi)</span>
                            <span class="nav-link-badge ml-auto text-xs text-gray-400">Coming Soon</span>
                        </a>

                        <!-- Education AI (School) - Coming Soon -->
                        <a href="#"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-50 cursor-not-allowed opacity-60">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            </svg>
                            <span class="nav-link-text">School AI</span>
                            <span class="nav-link-badge ml-auto text-xs text-gray-400">Coming Soon</span>
                        </a>

                        <!-- Youth AI (VumbiDNA) - Coming Soon -->
                        <a href="#"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-400 hover:bg-gray-50 cursor-not-allowed opacity-60">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="nav-link-text">Youth AI (VumbiDNA)</span>
                            <span class="nav-link-badge ml-auto text-xs text-gray-400">Coming Soon</span>
                        </a>

                        <!-- Section: System -->
                        <div
                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 py-2 mt-4 nav-section-title">
                            System
                        </div>

                        <!-- System Status -->
                        <a href="{{ route('system.status') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('system.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="nav-link-text">System Status</span>
                            <span class="nav-link-badge ml-auto text-xs text-green-600">Operational</span>
                        </a>

                        <!-- Schedule Dashboard -->
                        <a href="{{ route('schedule.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('schedule.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="nav-link-text">Schedule Dashboard</span>
                            <span class="nav-link-badge ml-auto text-xs text-blue-600" id="schedule-status">Monitor</span>
                        </a>
                        <!-- Brand Settings -->
                        <a href="{{ route('brands.index') }}"
                            class="nav-link flex items-center px-4 py-2 text-sm font-medium rounded-lg {{ request()->routeIs('brands.*') ? 'bg-green-50 text-green-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="nav-link-icon w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            <span class="nav-link-text">Brand Settings</span>
                        </a>

                        <!-- AI Provider Status -->
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200 ai-provider-text">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500 ai-provider-label">AI Provider</span>
                                <span class="text-xs font-medium text-gray-700">{{ config('ai.provider', 'ollama') }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-500 ai-provider-label">Status</span>
                                <span class="text-xs font-medium text-green-600">Connected</span>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-500 ai-provider-label">Model</span>
                                <span
                                    class="text-xs font-medium text-gray-700">{{ config('ai.providers.' . config('ai.provider') . '.model', 'N/A') }}</span>
                            </div>
                        </div>
                    </nav>
                </aside>
            @endauth

            <!-- Main Content -->
            <main class="flex-1 min-h-screen">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // Update badge counts
        document.addEventListener('livewire:loaded', function () {
            // Get pending actions count
            Livewire.on('action-updated', function () {
                fetch('{{ route("actions.queue") }}')
                    .then(response => response.text())
                    .then(html => {
                        const count = document.querySelector('#pending-count');
                        if (count) {
                            // Extract count from the page
                            const match = html.match(/Pending.*?(\d+)/);
                            count.textContent = match ? match[1] : '0';
                        }
                    });
            });
        });
    </script>
    @livewireScripts
    @stack('scripts')
</body>

</html>
