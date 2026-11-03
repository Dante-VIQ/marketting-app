@extends('layouts.guest')

@section('title', 'About Revora')
@section('meta_description', 'Revora is an autonomous AI marketing platform built for solo founders and lean teams. Learn how it works and who built it.')

@section('content')
<div class="relative isolate overflow-hidden bg-slate-950 py-16 sm:py-28 text-slate-100">
    <div class="absolute -top-40 left-1/2 -z-10 transform-gpu -translate-x-1/2 blur-3xl" aria-hidden="true">
        <div class="aspect-[1097/845] w-[68.5rem] bg-gradient-to-tr from-[#6366f1] to-[#a855f7] opacity-20"
             style="clip-path:polygon(74.1% 44.1%,100% 61.6%,97.5% 26.9%,85.5% 0.1%,80.7% 2%,72.5% 32.5%,60.2% 62.4%,52.4% 68.1%,47.5% 58.3%,45.2% 34.5%,27.5% 76.7%,0.1% 64.9%,17.9% 100%,27.6% 76.8%,76.1% 97.7%,74.1% 44.1%)"></div>
    </div>

    <div class="mx-auto max-w-7xl px-6 lg:px-8">

        {{-- ── Hero ───────────────────────────────────────────────────── --}}
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center rounded-full bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-400 ring-1 ring-inset ring-indigo-500/20 mb-4">
                Autonomous Marketing Intelligence
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-white">
                Built for builders<br>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-indigo-400 bg-clip-text text-transparent">who do it all.</span>
            </h1>
            <p class="mt-6 text-lg leading-8 text-slate-300">
                Revora is an autonomous hierarchical agent that manages your marketing — monitoring brands, drafting content, and surfacing opportunities — so you can focus on building the product that matters.
            </p>
        </div>

        {{-- ── Problem / Solution ─────────────────────────────────────── --}}
        <div class="mx-auto mt-16 max-w-5xl grid grid-cols-1 gap-8 md:grid-cols-2">
            <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl shadow-xl">
                <div class="flex items-center gap-x-3 text-rose-400 font-semibold text-lg mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    The problem
                </div>
                <p class="text-slate-300 leading-relaxed">
                    Solo founders spend over <strong class="text-white">30% of working hours</strong> on repetitive marketing cycles — SEO audits, content planning, competitor tracking, social scheduling. Essential, but a punishing drain on the time you should be building.
                </p>
                <div class="mt-6 pt-4 border-t border-slate-800/80 text-sm text-slate-400 font-medium">
                    ⚡ Result: burnout, slow growth, missed opportunities.
                </div>
            </div>

            <div class="rounded-2xl bg-gradient-to-b from-indigo-950/40 to-slate-900/60 border border-indigo-500/30 p-8 backdrop-blur-xl shadow-xl relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>
                <div class="flex items-center gap-x-3 text-indigo-400 font-semibold text-lg mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    The Revora solution
                </div>
                <p class="text-slate-300 leading-relaxed">
                    Revora monitors your business data, reasons through opportunities using your AI provider of choice, and enforces a strict safety policy — running low-risk actions automatically and routing high-impact decisions to your approval queue.
                </p>
                <div class="mt-6 pt-4 border-t border-indigo-900/50 text-sm text-indigo-300 font-medium">
                    ✨ Result: autonomous execution with full control.
                </div>
            </div>
        </div>

        {{-- ── Who it's for ───────────────────────────────────────────── --}}
        <div class="mx-auto mt-20 max-w-5xl">
            <h2 class="text-2xl font-bold text-center text-white mb-10">Designed for lean builders</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ([
                    ['icon'=>'🚀','title'=>'Solo Founders',   'desc'=>'Reclaim 10+ hours a week to focus on what only you can do.'],
                    ['icon'=>'💼','title'=>'Small Agencies',  'desc'=>'Manage multiple client brands from a single dashboard.'],
                    ['icon'=>'🌱','title'=>'Bootstrappers',   'desc'=>'Enterprise-level AI marketing without the enterprise price tag.'],
                    ['icon'=>'🛠️','title'=>'Developers',      'desc'=>'Extend, fork, and self-host the open architecture your way.'],
                ] as $a)
                <div class="rounded-xl bg-slate-900/40 border border-slate-800/80 p-5 hover:border-slate-700 transition">
                    <div class="text-2xl mb-2">{{ $a['icon'] }}</div>
                    <h3 class="font-semibold text-white">{{ $a['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-400">{{ $a['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ── Tech stack + Creator ───────────────────────────────────── --}}
        <div class="mx-auto mt-20 max-w-5xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Stack -->
            <div class="lg:col-span-7 rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Technology stack
                </h2>
                <dl class="space-y-4">
                    @foreach ([
                        ['Backend',      'Laravel 13 · MySQL · PHP 8.4'],
                        ['Agent Layer',  'Strands Agents SDK · Python 3.10+'],
                        ['AI Providers', 'OpenAI · Gemini · Anthropic · Ollama · Fireworks · Baseten'],
                        ['Frontend',     'Livewire 3 · Alpine.js · Tailwind v4'],
                        ['Data',         'Tavily Search · GA4 · Ahrefs'],
                    ] as [$label, $value])
                    <div class="flex justify-between items-center py-2 border-b border-slate-800 last:border-0">
                        <dt class="text-sm font-medium text-slate-400">{{ $label }}</dt>
                        <dd class="text-sm font-semibold text-indigo-300 text-right">{{ $value }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>

            <!-- Creator -->
            <div class="lg:col-span-5 rounded-2xl bg-gradient-to-br from-slate-900/90 to-indigo-950/50 border border-indigo-500/20 p-8 backdrop-blur-xl relative overflow-hidden">
                <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl"></div>
                <h2 class="text-xl font-bold text-white mb-4">Meet the creator</h2>
                <p class="text-sm text-slate-300 leading-relaxed">
                    <strong class="text-white">Dante</strong> — self-taught developer and founder of Vumbi Ventures in Kenya. Built Revora to solve his own growth bottleneck. No fancy CS degree, just shipped code that works.
                </p>
                <blockquote class="mt-6 border-l-2 border-indigo-500 pl-4 italic text-xs text-indigo-200 leading-normal">
                    "I was spending 15 hours a week on marketing work that wasn't growing my business. Now the agent handles it, and I build."
                </blockquote>
                <div class="mt-6">
                    <a href="{{ route('contact') }}"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-400 hover:text-emerald-300 transition-colors">
                        Get in touch →
                    </a>
                </div>
            </div>
        </div>

        {{-- ── CTA ────────────────────────────────────────────────────── --}}
        <div class="mt-20 text-center">
            <h2 class="text-2xl font-bold text-white mb-4">See Revora in action</h2>
            <p class="text-slate-400 mb-8 max-w-md mx-auto text-sm">Paste your URL and get a free AI visibility report — no sign-up, no credit card, results in under 60 seconds.</p>
            <a href="{{ route('public.scanner.index') }}"
               class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold transition-colors shadow-lg shadow-emerald-900/40">
                Scan my website free →
            </a>
        </div>

    </div>
</div>
@endsection
