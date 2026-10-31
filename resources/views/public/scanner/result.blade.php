<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Visibility Report — {{ $result['domain'] ?? 'Your site' }} — Vumbi AI</title>
    <meta name="description" content="Your free AI visibility report from Vumbi AI.">
    @include('layouts.partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-950 text-slate-100 min-h-full">

    {{-- Ambient blurs --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 -left-24 w-[500px] h-[500px] bg-emerald-500/8 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-24 w-[400px] h-[400px] bg-indigo-500/8 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 min-h-screen flex flex-col">

        {{-- Nav --}}
        <nav class="px-6 py-4 flex items-center justify-between max-w-4xl mx-auto w-full">
            <a href="{{ route('landing') }}" class="flex items-center space-x-1.5">
                <span class="text-xl font-bold text-emerald-400 tracking-tight">Vumbi</span>
                <span class="text-xl font-bold text-white">AI</span>
            </a>
            <div class="flex items-center space-x-4 text-sm">
                <a href="{{ route('public.scanner.index') }}" class="text-slate-400 hover:text-slate-200 transition-colors">← Scan another</a>
                @guest
                    <a href="{{ route('register') }}" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition-colors">
                        Get full dashboard
                    </a>
                @endguest
            </div>
        </nav>

        <main class="flex-1 px-4 py-8 max-w-4xl mx-auto w-full">

            @php
                $ai    = $result['ai'] ?? [];
                $page  = $result['page'] ?? [];
                $score = $result['visibility_score'] ?? 0;
                $tier  = $result['tier'] ?? 'Not visible';
                $color = $result['tier_color'] ?? 'rose';

                $colorMap = [
                    'emerald' => ['ring' => 'ring-emerald-500/40', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-400', 'badge' => 'bg-emerald-950/60 border-emerald-800/50 text-emerald-300'],
                    'sky'     => ['ring' => 'ring-sky-500/40',     'bg' => 'bg-sky-500/10',     'text' => 'text-sky-400',     'badge' => 'bg-sky-950/60 border-sky-800/50 text-sky-300'],
                    'amber'   => ['ring' => 'ring-amber-500/40',   'bg' => 'bg-amber-500/10',   'text' => 'text-amber-400',   'badge' => 'bg-amber-950/60 border-amber-800/50 text-amber-300'],
                    'rose'    => ['ring' => 'ring-rose-500/40',    'bg' => 'bg-rose-500/10',    'text' => 'text-rose-400',    'badge' => 'bg-rose-950/60 border-rose-800/50 text-rose-300'],
                ];
                $c = $colorMap[$color] ?? $colorMap['rose'];

                $priorityMap = [
                    'high'   => ['dot' => 'bg-rose-500',   'label' => 'text-rose-400',   'text' => 'High priority'],
                    'medium' => ['dot' => 'bg-amber-500',  'label' => 'text-amber-400',  'text' => 'Medium priority'],
                    'low'    => ['dot' => 'bg-slate-500',  'label' => 'text-slate-400',  'text' => 'Low priority'],
                ];
            @endphp

            {{-- Header --}}
            <div class="mb-8">
                <p class="text-slate-500 text-sm mb-1">AI Visibility Report</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 break-all">{{ $result['domain'] ?? $result['url'] }}</h1>
                <p class="text-slate-600 text-xs">Scanned {{ \Carbon\Carbon::parse($result['scanned_at'])->diffForHumans() }}</p>
            </div>

            {{-- Score card --}}
            <div class="mb-6 p-6 rounded-2xl bg-slate-900/60 border border-slate-800/60 backdrop-blur-sm flex flex-col sm:flex-row items-center gap-6">

                {{-- Circular score --}}
                <div class="relative shrink-0">
                    <svg class="w-28 h-28 -rotate-90" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="50" fill="none" stroke="rgb(30 41 59)" stroke-width="12"/>
                        <circle cx="60" cy="60" r="50" fill="none"
                            class="{{ str_replace('ring-', 'stroke-', $c['ring']) }}"
                            stroke-width="12"
                            stroke-linecap="round"
                            stroke-dasharray="{{ round(314 * $score / 100) }} 314"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-extrabold {{ $c['text'] }}">{{ $score }}</span>
                        <span class="text-xs text-slate-500 -mt-0.5">/ 100</span>
                    </div>
                </div>

                <div class="flex-1 text-center sm:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border {{ $c['badge'] }} text-xs font-semibold mb-2">
                        {{ $tier }}
                    </div>
                    <h2 class="text-xl font-bold text-white mb-1">AI Visibility Score</h2>
                    <p class="text-slate-400 text-sm">{{ $ai['score_rationale'] ?? 'Score based on how well your content communicates your business to AI systems.' }}</p>
                </div>
            </div>

            {{-- What AI sees --}}
            @if (!empty($ai['what_ai_sees']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-lg">🤖</span>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">What AI assistants would say about you</span>
                </div>
                <p class="text-slate-300 text-sm italic">"{{ $ai['what_ai_sees'] }}"</p>
            </div>
            @endif

            {{-- Business summary --}}
            @if (!empty($ai['business_summary']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-lg">🏢</span>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">What we found</span>
                </div>
                <p class="text-slate-300 text-sm">{{ $ai['business_summary'] }}</p>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                {{-- Top topics --}}
                @if (!empty($ai['top_topics']))
                <div class="p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-lg">🏷️</span>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Topics AI detected</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($ai['top_topics'] as $topic)
                            <span class="px-2.5 py-1 rounded-full bg-slate-800 border border-slate-700 text-slate-300 text-xs">{{ $topic }}</span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Content gaps --}}
                @if (!empty($ai['content_gaps']))
                <div class="p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-lg">🕳️</span>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Content gaps</span>
                    </div>
                    <ul class="space-y-1.5">
                        @foreach ($ai['content_gaps'] as $gap)
                            <li class="flex items-start gap-2 text-sm text-slate-400">
                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                {{ $gap }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            {{-- Recommendations --}}
            @if (!empty($ai['recommendations']))
            <div class="mb-8 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-lg">⚡</span>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Quick wins</span>
                </div>
                <div class="space-y-4">
                    @foreach ($ai['recommendations'] as $i => $rec)
                        @php
                            $p = $priorityMap[$rec['priority'] ?? 'medium'] ?? $priorityMap['medium'];
                        @endphp
                        <div class="flex gap-4">
                            <div class="shrink-0 w-6 h-6 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">
                                {{ $i + 1 }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="font-semibold text-slate-200 text-sm">{{ $rec['action'] ?? '' }}</span>
                                    <span class="flex items-center gap-1 text-xs {{ $p['label'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $p['dot'] }}"></span>
                                        {{ $p['text'] }}
                                    </span>
                                </div>
                                @if (!empty($rec['detail']))
                                    <p class="text-slate-500 text-xs">{{ $rec['detail'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- CTA --}}
            @guest
            <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-950/60 to-slate-900/60 border border-emerald-800/40 text-center">
                <h2 class="text-xl font-bold text-white mb-2">Want the full picture?</h2>
                <p class="text-slate-400 text-sm mb-5 max-w-md mx-auto">
                    Connect GA4, track competitors, generate content, and get a bot that knows your entire brand — free to start.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ route('register') }}"
                       class="px-6 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 transition-all shadow-lg shadow-emerald-900/40">
                        Start free — no credit card
                    </a>
                    <a href="{{ route('public.scanner.index') }}"
                       class="px-6 py-3 rounded-xl font-semibold text-slate-300 bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-all">
                        Scan another URL
                    </a>
                </div>
            </div>
            @else
            <div class="p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60 text-center">
                <p class="text-slate-400 text-sm mb-3">Want Vumbi to track this site continuously and alert you to changes?</p>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors text-sm">
                    Open dashboard
                </a>
            </div>
            @endguest

        </main>

        {{-- Footer --}}
        <footer class="py-6 text-center text-xs text-slate-700 mt-4">
            Built by <a href="https://vumbidna.com" class="text-slate-600 hover:text-slate-400 transition-colors">Vumbi AI</a>
            · <a href="{{ route('public.scanner.index') }}" class="text-slate-600 hover:text-slate-400 transition-colors">Scan another URL</a>
        </footer>
    </div>

</body>
</html>
