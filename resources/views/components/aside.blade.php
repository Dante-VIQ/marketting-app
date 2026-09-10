<aside
    class="w-64 bg-white/60 dark:bg-slate-900/60 backdrop-blur-md border-r border-slate-200/60 dark:border-slate-800/60 flex-shrink-0 h-full overflow-y-auto transition-colors duration-200">
    <nav class="p-4 space-y-1">
        <!-- ==================== CORE ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2">
            Core
        </div>

        <a href="{{ route('dashboard') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- ==================== ANALYTICS & INTELLIGENCE ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Analytics & Intelligence
        </div>

        <a href="{{ route('analytics.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('analytics.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('analytics.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span>Analytics</span>
            <span
                class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">Live</span>
        </a>

        <a href="{{ route('briefs.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('briefs.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('briefs.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <span>AI Brief</span>
            <span
                class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 border border-sky-200/50 dark:border-sky-800/50">Daily</span>
        </a>

        <a href="{{ route('seo.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('seo.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('seo.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>SEO Assistant</span>
            <span
                class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200/50 dark:border-amber-800/50">Monitor</span>
        </a>

        <!-- ==================== MARKETING ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Marketing
        </div>

        <a href="{{ route('leads.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('leads.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('leads.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Leads</span>
        </a>

        <a href="{{ route('campaigns.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('campaigns.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('campaigns.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
            </svg>
            <span>Campaigns</span>
        </a>

        <a href="{{ route('blog.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('blog.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('blog.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v14m0 0V6m0 14H5m14 0h3m-3 0h-3M5 10h10M5 14h6m-6 4h10" />
            </svg>
            <span>Blog</span>
            <span
                class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">Content</span>
        </a>

        <a href="{{ route('content.drafts') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('content.drafts') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('content.drafts') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v14m0 0V6m0 14H5m14 0h3m-3 0h-3M5 10h10M5 14h6m-6 4h10" />
            </svg>
            <span>Drafts</span>
            <span
                class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">Content</span>
        </a>
        <a href="{{ route('affiliate.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('affiliate.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('affiliate.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <span>Affiliate</span>
        </a>

        <!-- ==================== WORKFLOW ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Workflow
        </div>

        <a href="{{ route('actions.queue') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('actions.queue') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('actions.queue') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
            </svg>
            <span>Action Queue</span>
            <span
                class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200/50 dark:border-amber-800/50"
                id="pending-count">0</span>
        </a>

        <a href="{{ route('actions.history') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('actions.history') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('actions.history') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Action History</span>
        </a>

        <!-- ==================== SCANNER ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Scanner
        </div>

        <a href="{{ route('scanner.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('scanner.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('scanner.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <span>Page Scanner</span>
        </a>

        <!-- ==================== SYSTEM ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            System
        </div>

        <a href="{{ route('brands.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('brands.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('brands.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span>Brands</span>
        </a>

        <a href="{{ route('system.status') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('system.status') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('system.status') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 2.824 10.29 7 12.68a12.02 12.02 0 007-12.68 12.02 12.02 0 00-.382-3.016z" />
            </svg>
            <span>System Status</span>
        </a>

        <a href="{{ route('system.incidents') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('system.incidents') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('system.incidents') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Incidents</span>
        </a>

        <a href="{{ route('system.policies') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('system.policies') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('system.policies') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 2.824 10.29 7 12.68a12.02 12.02 0 007-12.68 12.02 12.02 0 00-.382-3.016z" />
            </svg>
            <span>Policies</span>
        </a>

        <!-- ==================== GUIDES ==================== -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Guides
        </div>

        <a href="{{ route('guides.index') }}"
            class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('guides.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('guides.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <span>Travel Guides</span>
        </a>

        <!-- ==================== AI PROVIDER STATUS ==================== -->
        <div
            class="mt-6 p-3 bg-white/40 dark:bg-slate-800/40 backdrop-blur-xs rounded-xl border border-slate-200/60 dark:border-slate-800">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">AI Provider</span>
                <span
                    class="font-medium text-slate-700 dark:text-slate-200">{{ config('ai.provider', 'ollama') }}</span>
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