<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Growth Strategy Report — {{ $result['domain'] ?? 'Your site' }} — Vumbi AI</title>
    <meta name="description" content="Your free AI visibility and customer acquisition strategy report from Vumbi AI.">
    @include('layouts.partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-950 text-slate-100 min-h-full">

    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 -left-24 w-[500px] h-[500px] bg-emerald-500/6 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-24 w-[400px] h-[400px] bg-indigo-500/6 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 min-h-screen flex flex-col">

        {{-- Nav --}}
        <nav class="px-6 py-4 flex items-center justify-between max-w-5xl mx-auto w-full">
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

        <main class="flex-1 px-4 pb-16 max-w-5xl mx-auto w-full">

            @php
                $ai       = $result['ai'] ?? [];
                $page     = $result['page'] ?? [];
                $intel    = $result['intelligence'] ?? [];
                $profile  = $intel['business_profile'] ?? [];
                $research = $intel['market_research'] ?? [];
                $strategy = $intel['acquisition_strategy'] ?? [];
                $score    = $result['visibility_score'] ?? 0;
                $tier     = $result['tier'] ?? 'Not visible';
                $color    = $result['tier_color'] ?? 'rose';

                $colorMap = [
                    'emerald' => ['gauge' => 'stroke-emerald-400', 'text' => 'text-emerald-400', 'badge' => 'bg-emerald-950/60 border-emerald-800/50 text-emerald-300', 'dot' => 'bg-emerald-500'],
                    'sky'     => ['gauge' => 'stroke-sky-400',     'text' => 'text-sky-400',     'badge' => 'bg-sky-950/60 border-sky-800/50 text-sky-300',         'dot' => 'bg-sky-500'],
                    'amber'   => ['gauge' => 'stroke-amber-400',   'text' => 'text-amber-400',   'badge' => 'bg-amber-950/60 border-amber-800/50 text-amber-300',   'dot' => 'bg-amber-500'],
                    'rose'    => ['gauge' => 'stroke-rose-400',    'text' => 'text-rose-400',    'badge' => 'bg-rose-950/60 border-rose-800/50 text-rose-300',       'dot' => 'bg-rose-500'],
                ];
                $c = $colorMap[$color] ?? $colorMap['rose'];

                $effortColor = fn($e) => match($e) { 'low' => 'text-emerald-400', 'medium' => 'text-amber-400', 'high' => 'text-rose-400', default => 'text-slate-400' };
                $impactColor = fn($i) => match($i) { 'high' => 'text-emerald-400', 'medium' => 'text-sky-400', 'low' => 'text-slate-400', default => 'text-slate-400' };
                $evidenceBadge = fn($t) => match($t) {
                    'research'       => 'bg-sky-950/60 border-sky-800/50 text-sky-300',
                    'inference'      => 'bg-slate-800/60 border-slate-700/50 text-slate-400',
                    'inference-only' => 'bg-slate-800/60 border-slate-700/50 text-slate-400',
                    default          => 'bg-slate-800/60 border-slate-700/50 text-slate-400',
                };
                $evidenceLabel = fn($t) => match($t) {
                    'research'       => 'Cited research',
                    'inference'      => 'AI inference',
                    'inference-only' => 'AI inference',
                    default          => 'AI inference',
                };

                $funnelStages = [
                    'awareness'     => ['icon' => '📡', 'label' => 'Awareness'],
                    'interest'      => ['icon' => '💡', 'label' => 'Interest'],
                    'consideration' => ['icon' => '🔍', 'label' => 'Consideration'],
                    'conversion'    => ['icon' => '✅', 'label' => 'Conversion'],
                    'retention'     => ['icon' => '🔁', 'label' => 'Retention'],
                ];
            @endphp

            {{-- ── Header ──────────────────────────────────────────────────── --}}
            <div class="mb-8 pt-4">
                <p class="text-slate-500 text-sm mb-1">Growth Strategy Report</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1 break-all">{{ $result['domain'] ?? $result['url'] }}</h1>
                <p class="text-slate-600 text-xs">
                    Scanned {{ \Carbon\Carbon::parse($result['scanned_at'])->diffForHumans() }}
                    @if (!empty($profile['business_type']) && $profile['business_type'] !== 'unknown')
                        · <span class="text-slate-500">Identified as: {{ $profile['business_type'] }}</span>
                    @endif
                </p>
            </div>

            {{-- ── Score + business summary ─────────────────────────────────── --}}
            <div class="mb-4 p-6 rounded-2xl bg-slate-900/60 border border-slate-800/60 backdrop-blur flex flex-col sm:flex-row items-center gap-6">
                <div class="relative shrink-0">
                    <svg class="w-28 h-28 -rotate-90" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="50" fill="none" stroke="rgb(30 41 59)" stroke-width="12"/>
                        <circle cx="60" cy="60" r="50" fill="none" class="{{ $c['gauge'] }}"
                            stroke-width="12" stroke-linecap="round"
                            stroke-dasharray="{{ round(314 * $score / 100) }} 314"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-3xl font-extrabold {{ $c['text'] }}">{{ $score }}</span>
                        <span class="text-xs text-slate-500 -mt-0.5">/ 100</span>
                    </div>
                </div>
                <div class="flex-1 text-center sm:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border {{ $c['badge'] }} text-xs font-semibold mb-2">
                        AI Visibility · {{ $tier }}
                    </div>
                    <p class="text-slate-300 text-sm mb-1">{{ $ai['business_summary'] ?? '' }}</p>
                    @if (!empty($ai['score_rationale']))
                        <p class="text-slate-500 text-xs">{{ $ai['score_rationale'] }}</p>
                    @endif
                </div>
            </div>

            {{-- ── What AI sees right now ───────────────────────────────────── --}}
            @if (!empty($ai['what_ai_sees']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">🤖 What AI assistants say about you today</div>
                <p class="text-slate-300 text-sm italic">"{{ $ai['what_ai_sees'] }}"</p>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                {{-- Top topics --}}
                @if (!empty($ai['top_topics']))
                <div class="p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">🏷️ Topics AI detected</div>
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
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">🕳️ Visibility gaps</div>
                    <ul class="space-y-1.5">
                        @foreach ($ai['content_gaps'] as $gap)
                            <li class="flex items-start gap-2 text-sm text-slate-400">
                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>{{ $gap }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- CUSTOMER ACQUISITION STRATEGY                                  --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}

            @if (!empty($strategy))
            <div class="mt-10 mb-4">
                <div class="flex items-center gap-3 mb-1">
                    <div class="h-px flex-1 bg-slate-800"></div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Customer Acquisition Strategy</span>
                    <div class="h-px flex-1 bg-slate-800"></div>
                </div>
                @if (!empty($strategy['headline']))
                    <p class="text-center text-slate-300 text-sm mt-2">{{ $strategy['headline'] }}</p>
                @endif
            </div>

            {{-- ── Business profile ─────────────────────────────────────────── --}}
            @if (!empty($profile) && ($profile['business_type'] ?? 'unknown') !== 'unknown')
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">🎯 Your customer</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @if (!empty($profile['target_customer']))
                    <div>
                        <div class="text-xs text-slate-600 mb-1">Who's buying</div>
                        <p class="text-slate-300 text-sm">{{ $profile['target_customer'] }}</p>
                    </div>
                    @endif
                    @if (!empty($profile['buying_journey']))
                    <div>
                        <div class="text-xs text-slate-600 mb-1">How long they take to decide</div>
                        <p class="text-slate-300 text-sm font-medium">{{ ucfirst($profile['buying_journey']) }}</p>
                        @if (!empty($profile['buying_journey_rationale']))
                            <p class="text-slate-500 text-xs mt-0.5">{{ $profile['buying_journey_rationale'] }}</p>
                        @endif
                    </div>
                    @endif
                    @if (!empty($profile['decision_triggers']))
                    <div class="sm:col-span-2">
                        <div class="text-xs text-slate-600 mb-2">What triggers their decision</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($profile['decision_triggers'] as $trigger)
                                <span class="px-2.5 py-1 rounded-full bg-indigo-950/60 border border-indigo-800/50 text-indigo-300 text-xs">{{ $trigger }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @if (!empty($profile['search_intent']))
                <div class="mt-3 pt-3 border-t border-slate-800">
                    <div class="text-xs text-slate-600 mb-1">What they type into Google</div>
                    <p class="text-slate-400 text-sm font-mono bg-slate-800/40 rounded-lg px-3 py-2">"{{ $profile['search_intent'] }}"</p>
                </div>
                @endif
                {{-- Confidence label --}}
                @if (!empty($profile['confidence']))
                <div class="mt-3 flex items-center gap-2">
                    <span class="text-xs text-slate-600">Profile confidence:</span>
                    <span class="text-xs px-2 py-0.5 rounded-full border
                        {{ $profile['confidence'] === 'high' ? 'bg-emerald-950/60 border-emerald-800/50 text-emerald-300' : ($profile['confidence'] === 'medium' ? 'bg-amber-950/60 border-amber-800/50 text-amber-300' : 'bg-slate-800 border-slate-700 text-slate-400') }}">
                        {{ ucfirst($profile['confidence']) }} confidence · AI inference from page content
                    </span>
                </div>
                @endif
            </div>
            @endif

            {{-- ── Market research ──────────────────────────────────────────── --}}
            @if (!empty($research['how_customers_discover']) || !empty($research['how_customers_decide']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">📚 How your customers find & choose</div>
                    @if (!empty($intel['research_available']))
                        <span class="text-xs px-2 py-0.5 rounded-full bg-sky-950/60 border border-sky-800/50 text-sky-300">Web research</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-400">AI inference</span>
                    @endif
                </div>
                @if (!empty($research['how_customers_discover']))
                <div class="mb-3">
                    <div class="text-xs text-slate-600 mb-1">Discovery</div>
                    <p class="text-slate-300 text-sm">{{ $research['how_customers_discover'] }}</p>
                </div>
                @endif
                @if (!empty($research['how_customers_decide']))
                <div>
                    <div class="text-xs text-slate-600 mb-1">Decision-making</div>
                    <p class="text-slate-300 text-sm">{{ $research['how_customers_decide'] }}</p>
                </div>
                @endif
                @if (!empty($research['market_context']))
                <div class="mt-3 pt-3 border-t border-slate-800">
                    <div class="text-xs text-slate-600 mb-1">Market context</div>
                    <p class="text-slate-400 text-sm">{{ $research['market_context'] }}</p>
                </div>
                @endif

                {{-- Sources --}}
                @if (!empty($research['sources']))
                <div class="mt-3 pt-3 border-t border-slate-800">
                    <div class="text-xs text-slate-600 mb-2">Sources</div>
                    <ul class="space-y-1">
                        @foreach (array_slice($research['sources'], 0, 5) as $source)
                            @if (!empty($source['url']))
                            <li class="text-xs">
                                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="text-sky-400 hover:text-sky-300 underline decoration-sky-800 hover:decoration-sky-400 transition-colors break-all">
                                    {{ $source['title'] ?: $source['url'] }}
                                </a>
                            </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
            @endif

            {{-- ── Acquisition channels ─────────────────────────────────────── --}}
            @if (!empty($strategy['channels']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">📣 How to reach them — recommended channels</div>
                <div class="space-y-5">
                    @foreach ($strategy['channels'] as $i => $channel)
                    <div class="flex gap-4 pb-5 {{ !$loop->last ? 'border-b border-slate-800/60' : '' }}">
                        <div class="shrink-0 w-7 h-7 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-400">
                            {{ $i + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="font-semibold text-slate-100 text-sm">{{ $channel['name'] ?? '' }}</span>
                                @if (!empty($channel['role']))
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-400">{{ ucfirst($channel['role']) }}</span>
                                @endif
                                @if (!empty($channel['evidence_type']))
                                    <span class="text-xs px-2 py-0.5 rounded-full border {{ $evidenceBadge($channel['evidence_type']) }}">
                                        {{ $evidenceLabel($channel['evidence_type']) }}
                                    </span>
                                @endif
                            </div>
                            @if (!empty($channel['recommendation']))
                                <p class="text-slate-300 text-sm mb-2">{{ $channel['recommendation'] }}</p>
                            @endif
                            @if (!empty($channel['evidence']))
                                <p class="text-slate-500 text-xs mb-2 italic">{{ $channel['evidence'] }}</p>
                            @endif
                            <div class="flex flex-wrap gap-3 text-xs">
                                @if (!empty($channel['effort']))
                                    <span>Effort: <span class="{{ $effortColor($channel['effort']) }} font-medium">{{ ucfirst($channel['effort']) }}</span></span>
                                @endif
                                @if (!empty($channel['impact']))
                                    <span>Impact: <span class="{{ $impactColor($channel['impact']) }} font-medium">{{ ucfirst($channel['impact']) }}</span></span>
                                @endif
                                @if (!empty($channel['time_to_results']))
                                    <span class="text-slate-500">Results in: {{ $channel['time_to_results'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── Lead funnel ──────────────────────────────────────────────── --}}
            @if (!empty($strategy['lead_funnel']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">🔄 Your lead funnel</div>
                <div class="space-y-0">
                    @foreach ($funnelStages as $stageKey => $stageMeta)
                        @php $stageData = $strategy['lead_funnel'][$stageKey] ?? null; @endphp
                        @if ($stageData)
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-base shrink-0">
                                    {{ $stageMeta['icon'] }}
                                </div>
                                @if (!$loop->last)
                                    <div class="w-px flex-1 bg-slate-800 my-1"></div>
                                @endif
                            </div>
                            <div class="pb-5 flex-1 min-w-0">
                                <div class="font-semibold text-slate-200 text-sm mb-0.5">{{ $stageMeta['label'] }}</div>
                                @if (!empty($stageData['description']))
                                    <p class="text-slate-400 text-sm mb-2">{{ $stageData['description'] }}</p>
                                @endif
                                @if (!empty($stageData['tactics']))
                                    <ul class="space-y-1">
                                        @foreach ($stageData['tactics'] as $tactic)
                                            <li class="flex items-start gap-2 text-xs text-slate-500">
                                                <span class="mt-1 w-1 h-1 rounded-full bg-emerald-500/60 shrink-0"></span>
                                                {{ $tactic }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── Quick wins ───────────────────────────────────────────────── --}}
            @if (!empty($strategy['quick_wins']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">⚡ Quick wins — do this week</div>
                <div class="space-y-3">
                    @foreach ($strategy['quick_wins'] as $i => $win)
                    <div class="flex gap-3">
                        <div class="shrink-0 w-5 h-5 rounded-full bg-emerald-950/60 border border-emerald-800/50 flex items-center justify-center text-emerald-400 text-xs font-bold mt-0.5">
                            {{ $i + 1 }}
                        </div>
                        <div>
                            <span class="text-slate-200 text-sm font-medium">{{ $win['action'] ?? '' }}</span>
                            @if (!empty($win['why']))
                                <p class="text-slate-500 text-xs mt-0.5">{{ $win['why'] }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── KPIs ─────────────────────────────────────────────────────── --}}
            @if (!empty($strategy['kpis']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">📊 What to measure</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($strategy['kpis'] as $kpi)
                    <div class="p-3 rounded-xl bg-slate-800/40 border border-slate-700/50">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="font-semibold text-slate-200 text-sm">{{ $kpi['metric'] ?? '' }}</span>
                            @if (!empty($kpi['evidence_type']))
                                <span class="text-xs px-1.5 py-0.5 rounded border {{ $evidenceBadge($kpi['evidence_type']) }} shrink-0">
                                    {{ $evidenceLabel($kpi['evidence_type']) }}
                                </span>
                            @endif
                        </div>
                        @if (!empty($kpi['what_it_measures']))
                            <p class="text-slate-500 text-xs mb-1">{{ $kpi['what_it_measures'] }}</p>
                        @endif
                        @if (!empty($kpi['benchmark']))
                            <p class="text-xs text-slate-600">Benchmark: <span class="text-slate-400">{{ $kpi['benchmark'] }}</span></p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── What we can't verify ─────────────────────────────────────── --}}
            @if (!empty($strategy['data_we_cannot_verify']))
            <div class="mb-4 p-5 rounded-2xl bg-slate-900/30 border border-slate-800/40">
                <div class="text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">⚠️ What we cannot verify without your data</div>
                <ul class="space-y-1">
                    @foreach ($strategy['data_we_cannot_verify'] as $item)
                        <li class="flex items-start gap-2 text-xs text-slate-600">
                            <span class="mt-1 w-1 h-1 rounded-full bg-slate-600 shrink-0"></span>
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
                <p class="text-xs text-slate-700 mt-3">Connect GA4, social media and ad accounts in Vumbi to replace inferences with your real data.</p>
            </div>
            @endif

            {{-- Confidence footer --}}
            @if (!empty($strategy['confidence_overall']))
            <div class="mb-6 flex items-center gap-2 text-xs text-slate-600">
                <span>Strategy confidence:</span>
                <span class="px-2 py-0.5 rounded-full border
                    {{ $strategy['confidence_overall'] === 'high' ? 'bg-emerald-950/40 border-emerald-900/50 text-emerald-500' : ($strategy['confidence_overall'] === 'medium' ? 'bg-amber-950/40 border-amber-900/50 text-amber-500' : 'bg-slate-800/60 border-slate-700/50 text-slate-500') }}">
                    {{ ucfirst($strategy['confidence_overall']) }}
                </span>
                @if (!empty($strategy['confidence_rationale']))
                    <span class="text-slate-700">— {{ $strategy['confidence_rationale'] }}</span>
                @endif
            </div>
            @endif

            @endif {{-- end if strategy --}}

            {{-- ── Content recommendations (from visibility section) ────────── --}}
            @if (!empty($ai['content_recommendations']))
            <div class="mb-6 p-5 rounded-2xl bg-slate-900/40 border border-slate-800/60">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">✏️ Improve your AI visibility</div>
                <div class="space-y-3">
                    @foreach ($ai['content_recommendations'] as $i => $rec)
                    <div class="flex gap-3">
                        <div class="shrink-0 w-5 h-5 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-500 mt-0.5">
                            {{ $i + 1 }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-slate-200 text-sm font-medium">{{ $rec['action'] ?? '' }}</span>
                                @if (!empty($rec['priority']))
                                    <span class="text-xs {{ $rec['priority'] === 'high' ? 'text-rose-400' : 'text-amber-400' }}">{{ ucfirst($rec['priority']) }} priority</span>
                                @endif
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

            {{-- ── CTA ──────────────────────────────────────────────────────── --}}
            @guest
            <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-950/50 to-slate-900/60 border border-emerald-800/30 text-center">
                <h2 class="text-xl font-bold text-white mb-2">Turn this strategy into action</h2>
                <p class="text-slate-400 text-sm mb-5 max-w-md mx-auto">
                    Connect GA4, track competitors, publish content, and get a bot that knows your entire brand.
                    Your data replaces every inference with verified numbers.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="{{ route('register') }}"
                       class="px-6 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 transition-all shadow-lg shadow-emerald-900/30">
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
                <p class="text-slate-400 text-sm mb-3">Track this site in your dashboard and get alerts when its visibility changes.</p>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors text-sm">
                    Open dashboard
                </a>
            </div>
            @endguest

        </main>

        <footer class="py-6 text-center text-xs text-slate-700 border-t border-slate-900">
            Built by <a href="https://vumbidna.com" class="text-slate-600 hover:text-slate-400 transition-colors">Vumbi AI</a>
            · <a href="{{ route('public.scanner.index') }}" class="text-slate-600 hover:text-slate-400 transition-colors">Scan another URL</a>
        </footer>
    </div>

</body>
</html>
