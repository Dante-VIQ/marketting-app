<aside class="w-64 bg-white/60 dark:bg-slate-900/60 backdrop-blur-md border-r border-slate-200/60 dark:border-slate-800/60 flex-shrink-0 h-full overflow-y-auto transition-colors duration-200">
    <nav class="p-4 space-y-1">
        <!-- Core -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2">
            Core
        </div>
        <a href="{{ route('dashboard') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('dashboard') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- Analytics & Intelligence -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Analytics & Intelligence
        </div>
        <a href="{{ route('analytics.index') }}" class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ request()->routeIs('analytics.*') ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
            <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ request()->routeIs('analytics.*') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <span>Analytics</span>
            <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">Live</span>
        </a>
        <a href="{{ route('briefs.index') }}" class="...">AI Brief</a>
        <a href="{{ route('seo.index') }}" class="...">SEO Assistant</a>

        <!-- Marketing -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Marketing
        </div>
        <a href="{{ route('leads.index') }}" class="...">Leads</a>
        <a href="{{ route('campaigns.index') }}" class="...">Campaigns</a>
        <a href="{{ route('blog.index') }}" class="...">Blog</a>
        <a href="{{ route('affiliate.index') }}" class="...">Affiliate</a>

        <!-- Workflow -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Workflow
        </div>
        <a href="{{ route('actions.queue') }}" class="...">Action Queue</a>
        <a href="{{ route('actions.history') }}" class="...">Action History</a>

        <!-- Scanner -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Scanner
        </div>
        <a href="{{ route('scanner.index') }}" class="...">Page Scanner</a>

        <!-- System -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            System
        </div>
        <a href="{{ route('brands.index') }}" class="...">Brands</a>
        <a href="{{ route('system.status') }}" class="...">System Status</a>
        <a href="{{ route('system.incidents') }}" class="...">Incidents</a>
        <a href="{{ route('system.policies') }}" class="...">Policies</a>

        <!-- Guides -->
        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider px-3 py-2 mt-5">
            Guides
        </div>
        <a href="{{ route('guides.index') }}" class="...">Travel Guides</a>

        <!-- AI Provider Status -->
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