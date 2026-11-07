@extends('layouts.guest')

@section('title', 'AI Marketing That Runs Itself')
@section('meta_description', 'Revora is an autonomous AI marketing platform for solo founders and lean teams. Monitor, decide, execute — without burning out.')

@section('content')
<div class="relative isolate overflow-hidden bg-slate-950 text-slate-100">

    <!-- Ambient Gradients -->
    <div class="absolute -top-40 left-1/2 -z-10 transform-gpu -translate-x-1/2 blur-3xl" aria-hidden="true">
        <div class="aspect-[1097/845] w-[68.5rem] bg-gradient-to-tr from-[#10b981] via-[#6366f1] to-[#a855f7] opacity-20"
             style="clip-path:polygon(74.1% 44.1%,100% 61.6%,97.5% 26.9%,85.5% 0.1%,80.7% 2%,72.5% 32.5%,60.2% 62.4%,52.4% 68.1%,47.5% 58.3%,45.2% 34.5%,27.5% 76.7%,0.1% 64.9%,17.9% 100%,27.6% 76.8%,76.1% 97.7%,74.1% 44.1%)"></div>
    </div>

    {{-- ── Hero ─────────────────────────────────────────────────────────── --}}
    <section class="relative px-6 pt-20 pb-24 sm:pt-32 sm:pb-32 lg:px-8">
        <div class="mx-auto max-w-4xl text-center">

            <!-- Badges -->
            <div class="mb-8 flex flex-wrap items-center justify-center gap-3">
                <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-3.5 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    🏆 Agents for Humans 2026
                </span>
                <span class="inline-flex items-center rounded-full bg-indigo-500/10 px-3.5 py-1 text-xs font-medium text-indigo-400 ring-1 ring-inset ring-indigo-500/20">
                    ⚡ Saves 10+ hrs / week per brand
                </span>
            </div>

            <!-- Heading -->
            <h1 class="text-5xl font-extrabold tracking-tight sm:text-6xl text-white leading-tight">
                Your AI marketing team.<br>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-indigo-400 bg-clip-text text-transparent">Always on.</span>
            </h1>

            <p class="mt-6 text-lg leading-8 text-slate-300 max-w-2xl mx-auto">
                Revora monitors your brands, surfaces opportunities, drafts content, and queues actions for your approval — all without you lifting a finger.
            </p>

            <!-- CTAs -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('public.scanner.index') }}"
                   class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:from-emerald-400 hover:to-teal-500 transition-all duration-200">
                    Scan your website free →
                </a>
                <a href="{{ route('register') }}"
                   class="rounded-xl bg-slate-900 border border-slate-800 px-6 py-3.5 text-sm font-semibold text-slate-300 hover:text-white hover:border-slate-700 transition duration-200">
                    Create account
                </a>
            </div>

            <p class="mt-4 text-xs text-slate-600">No credit card · 3 free scans · Up and running in minutes</p>
        </div>

        {{-- Agent activity preview --}}
        <div class="mx-auto mt-16 max-w-3xl rounded-2xl border border-slate-800 bg-slate-900/90 p-4 shadow-2xl backdrop-blur-xl relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl"></div>

            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4 px-2">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
                    <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
                    <span class="h-3 w-3 rounded-full bg-emerald-500/80"></span>
                    <span class="ml-2 text-xs font-mono text-slate-400">revora-agent · brand loop</span>
                </div>
                <span class="text-xs font-mono text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">LIVE</span>
            </div>

            <div class="space-y-2 font-mono text-xs sm:text-sm p-2 text-slate-300">
                <div class="text-slate-500"># Revora agent cycle starting...</div>
                <div class="flex items-center gap-2"><span class="text-indigo-400">🔄 SCAN</span><span>Vumbi Ventures — checking 6 signals</span></div>
                <div class="flex items-center gap-2"><span class="text-emerald-400">📡 FOUND</span><span>Content gap: "Top Safari Destinations 2026"</span></div>
                <div class="text-slate-400 pl-4">⚙️ Drafting blog post outline — safety policy: approved</div>
                <div class="pl-6 text-emerald-300 bg-emerald-950/30 py-1 px-2 rounded border-l-2 border-emerald-400">
                    → Draft queued for human approval · 1,200 words · SEO score 87
                </div>
                <div class="pl-6 text-amber-300 flex items-center gap-2">
                    <span>🧠 AI reasoning</span>
                    <span class="text-xs bg-amber-500/10 text-amber-400 px-1.5 py-0.5 rounded border border-amber-500/20">Confidence: 0.91</span>
                </div>
                <div class="text-indigo-300 pl-4">⚡ 3 social posts scheduled · competitor gap logged</div>
                <div class="pt-2 text-emerald-400 font-semibold">✅ CYCLE COMPLETE — 6 tasks · 0 errors · awaiting approval</div>
            </div>
        </div>
    </section>

    {{-- ── Free scan CTA strip ───────────────────────────────────────────── --}}
    <section class="border-y border-slate-800/60 bg-gradient-to-r from-emerald-950/40 via-slate-900/60 to-indigo-950/40 px-6 py-10 text-center">
        <p class="text-sm text-slate-400 mb-3">Not sure if AI can find your business? Get a free report in 60 seconds.</p>
        <a href="{{ route('public.scanner.index') }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm transition-colors shadow-lg shadow-emerald-900/40">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1116.65 16.65z"/>
            </svg>
            Check my AI visibility — free
        </a>
    </section>

    {{-- ── How it works ─────────────────────────────────────────────────── --}}
    <section class="bg-slate-900/40 px-6 py-24 sm:py-32 lg:px-8 backdrop-blur-sm">
        <div class="mx-auto max-w-7xl">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">How Revora works</h2>
                <p class="mt-4 text-slate-400">Four integrated steps that keep your marketing running 24 / 7.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['color'=>'emerald','icon'=>'🔍','step'=>'1','title'=>'Monitor','desc'=>'Continuous scans for content gaps, keyword opportunities, competitor moves, and lead signals across all your brands.'],
                    ['color'=>'indigo', 'icon'=>'🧠','step'=>'2','title'=>'Decide',  'desc'=>'AI reasons through each opportunity against your brief, your audience, and your safety policy before proposing any action.'],
                    ['color'=>'amber',  'icon'=>'⚡','step'=>'3','title'=>'Execute', 'desc'=>'Low-risk actions run automatically. High-impact drafts — blog posts, social copy, ad briefs — land in your approval queue.'],
                    ['color'=>'teal',   'icon'=>'📈','step'=>'4','title'=>'Learn',   'desc'=>'Every approval, rejection, and performance signal feeds back into the brief so the agent improves with every cycle.'],
                ] as $s)
                <div class="bg-slate-900/60 p-8 rounded-2xl border border-slate-800 hover:border-{{ $s['color'] }}-500/40 transition duration-300 shadow-xl group">
                    <div class="w-12 h-12 rounded-xl bg-{{ $s['color'] }}-500/10 border border-{{ $s['color'] }}-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition duration-300">
                        {{ $s['icon'] }}
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">{{ $s['step'] }}. {{ $s['title'] }}</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">{{ $s['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── What you get ─────────────────────────────────────────────────── --}}
    <section class="px-6 py-24 sm:py-32 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-white sm:text-4xl">Everything in one platform</h2>
                <p class="mt-4 text-slate-400">From discovery to published — all with human oversight.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ([
                    ['icon'=>'🎯','title'=>'AI Visibility Scoring',  'desc'=>'Know exactly how AI search engines describe your business and where you rank vs competitors.'],
                    ['icon'=>'✍️','title'=>'Autonomous Content Drafts','desc'=>'Blog posts, social copy, email campaigns — drafted by AI and queued for your one-click approval.'],
                    ['icon'=>'🔭','title'=>'Competitor Intelligence',  'desc'=>'Monitor rival brands, spot their gaps, and get actionable ideas before they do.'],
                    ['icon'=>'📊','title'=>'Brand Briefs',             'desc'=>'Living documents that capture your tone, audience, and goals — updated automatically as you grow.'],
                    ['icon'=>'🛡️','title'=>'Guardian Safety Layer',   'desc'=>'Every action is validated against your policy before it runs. Full audit log, always.'],
                    ['icon'=>'🌍','title'=>'Multi-Brand Ready',        'desc'=>'Run one agent across multiple brands simultaneously — each with its own brief and approval workflow.'],
                ] as $f)
                <div class="p-6 rounded-2xl bg-slate-900/50 border border-slate-800/60 hover:border-slate-700 transition">
                    <div class="text-3xl mb-3">{{ $f['icon'] }}</div>
                    <h3 class="font-semibold text-white mb-1.5">{{ $f['title'] }}</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Bottom CTA ───────────────────────────────────────────────────── --}}
    <section class="relative px-6 py-24 sm:py-32 lg:px-8 border-t border-slate-800/80 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950 via-indigo-950/20 to-slate-950 -z-10"></div>
        <div class="mx-auto max-w-4xl text-center">
            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                Your marketing shouldn't depend on you being awake.
            </h2>
            <p class="mt-4 text-lg text-slate-300 max-w-xl mx-auto">
                Start with a free AI visibility scan. No sign-up required.
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('public.scanner.index') }}"
                   class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-3.5 text-sm font-semibold text-white shadow-lg hover:from-emerald-400 hover:to-teal-500 transition duration-200">
                    Scan my website free →
                </a>
                <a href="{{ route('register') }}"
                   class="rounded-xl bg-slate-900 border border-slate-800 px-8 py-3.5 text-sm font-semibold text-slate-300 hover:text-white hover:border-slate-700 transition duration-200">
                    Create account
                </a>
            </div>
        </div>
    </section>

</div>
@endsection
