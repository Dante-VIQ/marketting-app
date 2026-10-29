
@php
    $u = auth()->user();

    $groups = [
        [
            'key' => 'insights',
            'label' => 'Insights',
            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            'items' => [
                ['route'=>'analytics.index', 'match'=>'analytics.index', 'label'=>'Analytics', 'icon'=>['M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'], 'badge'=>['Live','emerald',null]],
                ['route'=>'impact.index', 'match'=>'impact.*', 'label'=>'Impact', 'icon'=>['M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'], 'badge'=>['Proof','emerald',null]],
                ['route'=>'briefs.index', 'match'=>'briefs.*', 'label'=>'AI Brief', 'icon'=>['M13 10V3L4 14h7v7l9-11h-7z'], 'badge'=>['Daily','sky',null]],
                ['route'=>'seo.index', 'match'=>'seo.*', 'label'=>'SEO Assistant', 'icon'=>['M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'], 'badge'=>['Monitor','amber',null]],
                ['route'=>'visibility.index', 'match'=>'visibility.*', 'label'=>'AI Visibility', 'icon'=>['M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'], 'badge'=>['AEO','indigo',null]],
                ['route'=>'ahrefs.index', 'match'=>'ahrefs.*', 'label'=>'Ahrefs', 'icon'=>['M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'], 'badge'=>['Backlinks','sky',null]],
                ['route'=>'listening.index', 'match'=>'listening.*', 'label'=>'Social Listening', 'icon'=>['M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z'], 'badge'=>['Daily','sky',null]],
                ['route'=>'competitors.index', 'match'=>'competitors.*', 'label'=>'Competitors', 'icon'=>['M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'], 'badge'=>['Weekly','sky',null]],
                ['route'=>'personas.index', 'match'=>'personas.*', 'label'=>'Buyer Personas', 'icon'=>['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z']],
                ['route'=>'analytics.revenue-leaks', 'match'=>'analytics.revenue-leaks', 'label'=>'Revenue Leaks', 'icon'=>['M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z']],
                ['route'=>'calibration', 'match'=>'calibration', 'label'=>'Calibration', 'icon'=>['M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'], 'badge'=>['Trust','violet',null]],
            ],
        ],
        [
            'key' => 'content',
            'label' => 'Content',
            'icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
            'items' => [
                ['route'=>'content-plan.index', 'match'=>'content-plan.*', 'label'=>'Content Plan', 'icon'=>['M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z']],
                ['route'=>'blog.index', 'match'=>'blog.*', 'label'=>'Blog', 'icon'=>['M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v14m0 0V6m0 14H5m14 0h3m-3 0h-3M5 10h10M5 14h6m-6 4h10'], 'badge'=>['Content','emerald',null]],
                ['route'=>'content.drafts', 'match'=>'content.drafts', 'label'=>'Drafts', 'icon'=>['M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v14m0 0V6m0 14H5m14 0h3m-3 0h-3M5 10h10M5 14h6m-6 4h10'], 'badge'=>['Content','emerald',null]],
                ['route'=>'content.published', 'match'=>'content.published', 'label'=>'Published', 'icon'=>['M5 13l4 4L19 7']],
                ['route'=>'guides.index', 'match'=>'guides.*', 'label'=>'Travel Guides', 'icon'=>['M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7']],
                ['route'=>'affiliate.index', 'match'=>'affiliate.*', 'label'=>'Affiliate', 'icon'=>['M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1']],
            ],
        ],
        [
            'key' => 'growth',
            'label' => 'Ads & Leads',
            'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
            'items' => [
                ['route'=>'leads.index', 'match'=>'leads.*', 'label'=>'Leads', 'icon'=>['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z']],
                ['route'=>'campaigns.index', 'match'=>'campaigns.*', 'label'=>'Campaigns', 'icon'=>['M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z','M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z']],
                ['route'=>'ad-platforms.index', 'match'=>'ad-platforms.*', 'label'=>'Ad Platforms', 'icon'=>['M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'], 'badge'=>['Meta','sky',null]],
                ['route'=>'meta.index', 'match'=>'meta.*', 'label'=>'Meta', 'icon'=>['M13 10V3L4 14h7v7l9-11h-7z'], 'badge'=>['Free API','sky',null]],
            ],
        ],
        [
            'key' => 'automation',
            'label' => 'Automation',
            'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
            'items' => [
                ['route'=>'actions.queue', 'match'=>'actions.queue', 'label'=>'Action Queue', 'icon'=>['M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'], 'badge'=>['0','amber','pending-count']],
                ['route'=>'actions.history', 'match'=>'actions.history', 'label'=>'Action History', 'icon'=>['M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z']],
                ['route'=>'recurring-issues', 'match'=>'recurring-issues', 'label'=>'Recurring Issues', 'icon'=>['M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'], 'badge'=>['Alert','amber',null]],
                ['route'=>'schedule.index', 'match'=>'schedule.*', 'label'=>'Schedule', 'only'=>'superadmin', 'icon'=>['M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z']],
                ['route'=>'scanner.index', 'match'=>'scanner.*', 'label'=>'Page Scanner', 'icon'=>['M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z']],
            ],
        ],
        [
            'key' => 'system',
            'label' => 'System',
            'icon' => 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
            'items' => [
                ['route'=>'brands.index', 'match'=>'brands.*', 'label'=>'Brands', 'icon'=>['M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4']],
                ['route'=>'system.status', 'match'=>'system.status', 'label'=>'System Status', 'icon'=>['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 2.824 10.29 7 12.68a12.02 12.02 0 007-12.68 12.02 12.02 0 00-.382-3.016z']],
                ['route'=>'system.incidents', 'match'=>'system.incidents', 'label'=>'Incidents', 'icon'=>['M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z']],
                ['route'=>'system.policies', 'match'=>'system.policies', 'label'=>'Policies', 'icon'=>['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 2.824 10.29 7 12.68a12.02 12.02 0 007-12.68 12.02 12.02 0 00-.382-3.016z']],
                ['route'=>'marketing.settings', 'match'=>'marketing.*', 'label'=>'Marketing Settings', 'icon'=>['M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z']],
                ['route'=>'admin.users.index', 'match'=>'admin.users.*', 'label'=>'User Approvals', 'icon'=>['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'], 'badge'=>['Admin','slate',null], 'only'=>'approver'],
                ['route'=>'admin.docs', 'match'=>'admin.docs', 'label'=>'System Docs', 'icon'=>['M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'], 'badge'=>['Admin','slate',null], 'only'=>'superadmin'],
            ],
        ],
        [
            'key' => 'soon',
            'label' => 'Coming Soon',
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'items' => [
                ['route'=>'healthcare.index', 'match'=>'healthcare.*', 'label'=>'Healthcare AI', 'icon'=>['M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'], 'badge'=>['Soon','slate',null]],
                ['route'=>'education.index', 'match'=>'education.*', 'label'=>'Education AI', 'icon'=>['M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'], 'badge'=>['Soon','slate',null]],
                ['route'=>'youth.index', 'match'=>'youth.*', 'label'=>'Youth AI', 'icon'=>['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'], 'badge'=>['Soon','slate',null]],
            ],
        ],
    ];

    $badgeColors = [
        'emerald' => 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border-emerald-200/50 dark:border-emerald-800/50',
        'sky'     => 'bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 border-sky-200/50 dark:border-sky-800/50',
        'amber'   => 'bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border-amber-200/50 dark:border-amber-800/50',
        'violet'  => 'bg-violet-50 dark:bg-violet-950 text-violet-600 dark:text-violet-400 border-violet-200/50 dark:border-violet-800/50',
        'slate'   => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200/50 dark:border-slate-700/50',
    ];

    // Filter links according to the current user's permissions.
    $groups = collect($groups)->map(function ($g) use ($u) {
        $g['items'] = array_values(array_filter($g['items'], fn ($i) => match ($i['only'] ?? null) {
            'approver' => (bool) $u?->isApprover(),
            'superadmin' => (bool) $u?->isSuperAdmin(),
            default => true,
        }));

        return $g;
    })->filter(fn ($g) => !empty($g['items']))->values()->all();

    // Automatically identify the active group.
    $activeGroup = null;

    foreach ($groups as $g) {
        foreach ($g['items'] as $i) {
            if (request()->routeIs($i['match'])) {
                $activeGroup = $g['key'];
                break 2;
            }
        }
    }

    $dashActive = request()->routeIs('dashboard');
@endphp

<style>
    [x-cloak] {
        display: none !important;
    }
</style>

<aside
    id="app-sidebar"
    :class="navOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed top-16 bottom-0 left-0 z-40 w-72 max-w-[85vw]
           bg-white dark:bg-slate-900 lg:bg-white/60 lg:dark:bg-slate-900/60
           lg:static lg:z-auto lg:w-64 lg:max-w-none lg:h-full lg:flex-shrink-0
           backdrop-blur-md border-r border-slate-200/60 dark:border-slate-800/60
           overflow-y-auto overflow-x-hidden overscroll-contain
           transform transition-transform duration-200 ease-out"
    aria-label="Main sidebar">

    <nav
        class="p-4 space-y-1"
        x-data="{
            open: {},

            init() {
                try {
                    this.open = JSON.parse(localStorage.getItem('nav-open') || '{}') || {};
                } catch (e) {
                    this.open = {};
                }

                // Always open the group containing the current page.
                @if($activeGroup)
                    this.open['{{ $activeGroup }}'] = true;
                @endif
            },

            toggle(k) {
                this.open[k] = !this.open[k];

                try {
                    localStorage.setItem('nav-open', JSON.stringify(this.open));
                } catch (e) {}
            }
        }">

        {{-- Dashboard --}}
        <a
            href="{{ route('dashboard') }}"
            title="Dashboard"
            aria-label="Dashboard"
            class="group flex items-center justify-start
                   min-h-10 px-3 py-2 text-sm font-medium rounded-xl
                   transition-all duration-150
                   {{ $dashActive
                        ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-sm border border-emerald-100 dark:border-emerald-900/50'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">

            <svg
                class="w-5 h-5 mr-3 flex-shrink-0
                       {{ $dashActive
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>

            <span class="inline">Dashboard</span>
        </a>

        {{-- Navigation groups --}}
        @foreach($groups as $g)
            @php
                $has = $g['key'] === $activeGroup;
            @endphp

            <div class="pt-1">

                <button
                    type="button"
                    @click="toggle('{{ $g['key'] }}')"
                    :aria-expanded="open['{{ $g['key'] }}'] ? 'true' : 'false'"
                    aria-controls="nav-{{ $g['key'] }}"
                    title="{{ $g['label'] }}"
                    aria-label="{{ $g['label'] }}"
                    class="w-full group flex items-center justify-start
                           min-h-10 px-3 py-2 text-sm font-semibold rounded-xl
                           transition-all duration-150
                           {{ $has
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-slate-700 dark:text-slate-200 hover:bg-white/50 dark:hover:bg-slate-800/50' }}">

                    <svg
                        class="w-5 h-5 mr-3 flex-shrink-0
                               {{ $has
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-slate-400 dark:text-slate-500' }}"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="{{ $g['icon'] }}" />
                    </svg>

                    <span class="inline">{{ $g['label'] }}</span>

                    <span class="inline ml-2 text-[10px] font-medium text-slate-400 dark:text-slate-500">
                        {{ count($g['items']) }}
                    </span>

                    <svg
                        class="block w-4 h-4 ml-auto text-slate-400 transition-transform duration-150"
                        :class="open['{{ $g['key'] }}'] ? 'rotate-180' : ''"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                {{-- Submenu: icon-only on mobile --}}
                <div
                    id="nav-{{ $g['key'] }}"
                    x-show="open['{{ $g['key'] }}']"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="mt-1 ml-4 pl-2
                           border-l border-slate-200 dark:border-slate-700
                           space-y-0.5">

                    @foreach($g['items'] as $i)
                        @php
                            $on = request()->routeIs($i['match']);
                        @endphp

                        <a
                            href="{{ route($i['route']) }}"
                            title="{{ $i['label'] }}"
                            aria-label="{{ $i['label'] }}"
                            @if($on) aria-current="page" @endif
                            class="group flex items-center justify-start
                                   min-h-9 px-2 py-2 lg:py-1.5
                                   text-sm font-medium rounded-lg transition-all duration-150
                                   {{ $on
                                        ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-sm border border-emerald-100 dark:border-emerald-900/50'
                                        : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">

                            <svg
                                class="w-4 h-4 mr-2 flex-shrink-0
                                       {{ $on
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true">
                                @foreach($i['icon'] as $d)
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="{{ $d }}" />
                                @endforeach
                            </svg>

                            <span class="inline truncate">
                                {{ $i['label'] }}
                            </span>

                            @if(!empty($i['badge']))
                                <span
                                    @if($i['badge'][2]) id="{{ $i['badge'][2] }}" @endif
                                    class="inline-flex ml-auto text-[10px] font-semibold
                                           px-2 py-0.5 rounded-full border whitespace-nowrap shrink-0
                                           {{ $badgeColors[$i['badge'][1]] ?? '' }}">
                                    {{ $i['badge'][0] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- AI Provider Status: desktop only --}}
        <div
            class="mt-6 p-3 bg-white/40 dark:bg-slate-800/40
                   backdrop-blur-xs rounded-xl border border-slate-200/60 dark:border-slate-800">

            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">AI Provider</span>
                <span class="font-medium text-slate-700 dark:text-slate-200">
                    {{ config('ai.provider', 'ollama') }}
                </span>
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
