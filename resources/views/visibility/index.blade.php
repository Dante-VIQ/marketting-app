@extends('layouts.app')

@section('title', 'AI Visibility')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">AI Visibility</h1>
            <p class="mt-1 text-sm text-slate-400">
                When someone asks an AI assistant about {{ $brand->domain_type }} in your space, does
                <span class="text-white font-medium">{{ $brand->name }}</span> come up?
            </p>
        </div>
        <form method="POST" action="{{ route('visibility.run') }}">
            @csrf
            <button type="submit"
                    @if($running) disabled @endif
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white
                           bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500
                           shadow-lg shadow-emerald-500/20 transition-all duration-200 disabled:opacity-60">
                @if($running)
                    <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                    Audit running…
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Run AI Audit
                @endif
            </button>
        </form>
    </div>

    {{-- Flash messages --}}
    @foreach(['success' => 'emerald', 'warning' => 'amber', 'error' => 'red'] as $key => $color)
        @if(session($key))
            <div class="flex items-center gap-3 bg-{{ $color }}-500/10 border border-{{ $color }}-500/20 rounded-xl px-5 py-3 text-sm text-{{ $color }}-400">
                {{ session($key) }}
            </div>
        @endif
    @endforeach

    @if(!$latestAudit || ($latestAudit->status === 'failed' && !$latestAudit->report))

        {{-- ── Empty state ──────────────────────────────────────────────── --}}
        <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center mx-auto mb-5">
                <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-white">No audit yet</h2>
            <p class="mt-2 text-sm text-slate-400 max-w-md mx-auto">
                Run your first audit to discover how often AI assistants mention <strong class="text-white">{{ $brand->name }}</strong>
                when people ask about your niche.
            </p>
            <form method="POST" action="{{ route('visibility.run') }}" class="mt-6 inline-block">
                @csrf
                <button type="submit"
                        class="px-6 py-3 rounded-xl text-sm font-semibold text-white
                               bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500
                               shadow-lg shadow-emerald-500/20 transition-all">
                    Run First Audit →
                </button>
            </form>
        </div>

    @elseif($latestAudit->status === 'running')

        {{-- ── Running state ────────────────────────────────────────────── --}}
        <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto mb-5 animate-pulse">
                <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold text-white">Audit in progress</h2>
            <p class="mt-2 text-sm text-slate-400">
                Asking AI assistants about your brand… this takes 2–3 minutes.
            </p>
            <p class="mt-4 text-xs text-slate-600">Refresh the page to see results when it's done.</p>
        </div>

    @elseif($latestAudit && $latestAudit->status === 'done')

        @php
            $report      = $latestAudit->report ?? [];
            $queries     = $report['queries']         ?? [];
            $competitors = $report['competitors']     ?? [];
            $recs        = $report['recommendations'] ?? [];
            $tierColor   = $latestAudit->tierColor();
        @endphp

        {{-- ── Score hero ───────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

            <div class="sm:col-span-1 bg-slate-900/60 border border-slate-800/60 rounded-2xl p-6 flex flex-col items-center justify-center text-center">
                <div class="relative w-28 h-28 mb-4">
                    {{-- Simple circular score indicator --}}
                    <svg class="w-28 h-28 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#1e293b" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none"
                                stroke="{{ ['emerald' => '#10b981', 'sky' => '#0ea5e9', 'amber' => '#f59e0b', 'red' => '#ef4444'][$tierColor] }}"
                                stroke-width="3"
                                stroke-dasharray="{{ $latestAudit->score }}, 100"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-3xl font-extrabold text-white">{{ $latestAudit->score }}</span>
                    </div>
                </div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">AI Visibility Score</p>
                <span class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                    bg-{{ $tierColor }}-500/10 text-{{ $tierColor }}-400 ring-1 ring-inset ring-{{ $tierColor }}-500/20">
                    {{ $latestAudit->tier() }}
                </span>
                <p class="mt-3 text-xs text-slate-500">
                    Mentioned in {{ $latestAudit->probes_hit }} / {{ $latestAudit->probes_sent }} queries
                </p>
                <p class="mt-1 text-xs text-slate-600">
                    Audited {{ $latestAudit->audited_at?->diffForHumans() ?? '—' }}
                </p>
            </div>

            <div class="sm:col-span-2 bg-slate-900/60 border border-slate-800/60 rounded-2xl p-6">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Summary</p>
                <p class="text-sm text-slate-300 leading-relaxed">{{ $latestAudit->summary ?? 'No summary available.' }}</p>

                {{-- Competitor benchmark --}}
                @if(!empty($competitors))
                    <div class="mt-5">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Competitor Benchmark</p>
                        <div class="space-y-2">
                            @foreach($competitors as $comp)
                                @php
                                    $compScore = (int)($comp['score'] ?? 0);
                                    $isSelf = stripos($comp['name'] ?? '', $brand->name) !== false
                                           || stripos($brand->name, $comp['name'] ?? '') !== false;
                                @endphp
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-slate-400 w-28 shrink-0 truncate {{ $isSelf ? 'text-emerald-400 font-semibold' : '' }}">
                                        {{ $comp['name'] ?? '?' }}{{ $isSelf ? ' (you)' : '' }}
                                    </span>
                                    <div class="flex-1 h-2 bg-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500
                                            {{ $isSelf ? 'bg-emerald-500' : 'bg-slate-600' }}"
                                             style="width: {{ $compScore }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500 w-8 text-right">{{ $compScore }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Probe results ────────────────────────────────────────────── --}}
        @if(!empty($queries))
        <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800/60">
                <h2 class="text-sm font-semibold text-white">Probe Queries</h2>
                <p class="text-xs text-slate-500 mt-0.5">Questions asked to the AI — did your brand come up?</p>
            </div>
            <div class="divide-y divide-slate-800/40">
                @foreach($queries as $q)
                    <div class="px-6 py-4 flex items-start gap-4">
                        <div class="shrink-0 mt-0.5">
                            @if($q['mentioned'])
                                <div class="w-6 h-6 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            @else
                                <div class="w-6 h-6 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white">"{{ $q['query'] }}"</p>
                            @if(!empty($q['context']))
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed italic">{{ $q['context'] }}</p>
                            @elseif(!$q['mentioned'])
                                <p class="mt-1 text-xs text-slate-600">Brand not mentioned in response.</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs px-2 py-0.5 rounded-full
                            {{ ($q['priority'] ?? '') === 'high' ? 'bg-amber-500/10 text-amber-400' : 'bg-slate-800 text-slate-500' }}">
                            {{ $q['priority'] ?? 'medium' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Recommendations ──────────────────────────────────────────── --}}
        @if(!empty($recs))
        <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800/60">
                <h2 class="text-sm font-semibold text-white">AEO Recommendations</h2>
                <p class="text-xs text-slate-500 mt-0.5">Actions to improve how AI assistants discover and cite your brand.</p>
            </div>
            <div class="divide-y divide-slate-800/40">
                @foreach($recs as $i => $rec)
                    <div class="px-6 py-4 flex items-start gap-4">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-slate-800 text-slate-400 text-xs font-bold flex items-center justify-center">
                            {{ $i + 1 }}
                        </span>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-sm font-medium text-white">{{ $rec['action'] ?? '' }}</p>
                                @if(($rec['priority'] ?? '') === 'high')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-500/10 text-red-400 ring-1 ring-inset ring-red-500/20">High priority</span>
                                @endif
                            </div>
                            @if(!empty($rec['reason']))
                                <p class="text-xs text-slate-400">{{ $rec['reason'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    @endif

    {{-- ── Audit history ─────────────────────────────────────────────── --}}
    @if($auditHistory->count() > 1)
    <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-800/60">
            <h2 class="text-sm font-semibold text-white">Audit History</h2>
        </div>
        <div class="divide-y divide-slate-800/40">
            @foreach($auditHistory as $audit)
                <div class="px-6 py-3 flex items-center gap-4 text-sm">
                    <span class="text-slate-400 w-32 shrink-0">{{ $audit->audited_at?->format('M j, Y') ?? '—' }}</span>
                    <div class="flex-1 h-1.5 bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-500/60" style="width: {{ $audit->score }}%"></div>
                    </div>
                    <span class="text-white font-semibold w-10 text-right">{{ $audit->score }}</span>
                    <span class="text-slate-500 text-xs">{{ $audit->probes_hit }}/{{ $audit->probes_sent }} probes</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
