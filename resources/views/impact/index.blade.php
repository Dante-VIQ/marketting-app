@extends('layouts.app')

@section('title', 'Impact')

@section('content')
@php
    $states = ['not_started' => 'Not started', 'too_early' => 'Too early to tell', 'no_baseline' => 'Missing history', 'ok' => 'Comparison'];
    $a = $r['actions'];
    $soc = $r['social'];
    $rev = $r['revenue'];
@endphp
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6 max-w-5xl">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-slate-100">📈 Impact</h1>
        <p class="text-gray-600 dark:text-slate-400">Is Vumbi AI helping {{ $brand->name }}? This page compares real numbers before and after the system started working. It never fills gaps with guesses.</p>
    </div>

    @if(session('message'))<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    {{-- 1. Before / after --}}
    <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-5 space-y-3">
        <h2 class="font-semibold text-gray-900 dark:text-slate-100">Site traffic: before and after the system started</h2>
        @if($r['start'])
            <p class="text-sm text-gray-600 dark:text-slate-400">The system started on <strong>{{ $r['start'] }}</strong> (its first carried-out action or first approved post).</p>
        @endif

        @if($r['state'] !== 'ok')
            <p class="text-sm p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900"><strong>{{ $states[$r['state']] ?? $r['state'] }}.</strong> {{ $r['note'] }}</p>
        @else
            @php $w = $r['windows']; @endphp
            <p class="text-xs text-gray-500">{{ $w['len'] }} days before ({{ $w['before_from'] }} to {{ $w['before_to'] }}) against {{ $w['len'] }} days after ({{ $w['after_from'] }} to {{ $w['after_to'] }}).</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-2 pr-4">Measure</th><th class="py-2 pr-4">Before</th><th class="py-2 pr-4">After</th><th class="py-2">Change</th></tr></thead>
                    <tbody>
                    @foreach($r['rows'] as $row)
                        <tr class="border-t border-gray-100 dark:border-slate-800">
                            <td class="py-2 pr-4 text-gray-900 dark:text-slate-100">{{ $row['label'] }}</td>
                            <td class="py-2 pr-4">{{ number_format($row['before']) }}</td>
                            <td class="py-2 pr-4">{{ number_format($row['after']) }}</td>
                            <td class="py-2">
                                @if($row['change_pct'] === null)<span class="text-gray-400" title="There was nothing before to compare with">—</span>
                                @else<span class="{{ $row['change_pct'] >= 0 ? 'text-emerald-700' : 'text-red-700' }} font-medium">{{ $row['change_pct'] > 0 ? '+' : '' }}{{ $row['change_pct'] }}%</span>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(!empty($r['weekly']))
                <details class="text-sm"><summary class="cursor-pointer text-gray-600">Last 12 full weeks</summary>
                    <table class="mt-2 text-xs w-full max-w-md"><thead><tr class="text-left text-gray-500"><th class="py-1">Week starting</th><th>Visits</th><th>Key events</th></tr></thead><tbody>
                        @foreach($r['weekly'] as $wk)
                            <tr class="border-t border-gray-100 {{ $wk['started'] ? 'bg-emerald-50' : '' }}"><td class="py-1">{{ $wk['week'] }} @if($wk['started'])<span class="text-emerald-700">← system started</span>@endif</td>
                                <td>{{ $wk['sessions'] === null ? '—' : number_format($wk['sessions']) }}</td><td>{{ $wk['conversions'] === null ? '—' : number_format($wk['conversions']) }}</td></tr>
                        @endforeach
                    </tbody></table>
                    <p class="text-xs text-gray-400 mt-1">A dash means traffic data is missing for part of that week.</p>
                </details>
            @endif
        @endif
        <p class="text-xs text-gray-500">{{ $r['caveat'] }}</p>
    </section>

    {{-- 2. Actions --}}
    <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-5 space-y-2">
        <h2 class="font-semibold text-gray-900 dark:text-slate-100">Actions the system carried out</h2>
        @if($a['executed'] === 0)
            <p class="text-sm text-gray-500">None carried out yet.</p>
        @else
            <p class="text-sm text-gray-700 dark:text-slate-300">{{ $a['executed'] }} carried out. {{ $a['checked'] }} were checked a day later: <strong>{{ $a['improved'] }}</strong> improved their target metric, <strong>{{ $a['not_improved'] }}</strong> did not{{ $a['rolled_back'] ? ', ' . $a['rolled_back'] . ' rolled back' : '' }}. {{ $a['not_checked_yet'] }} not checked yet (not counted either way).</p>
        @endif
    </section>

    {{-- 3. Social --}}
    <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-5 space-y-2">
        <h2 class="font-semibold text-gray-900 dark:text-slate-100">Social posts and the website</h2>
        @if($soc['state'] === 'ok')
            <p class="text-sm text-gray-700 dark:text-slate-300">{{ $soc['tracked_posts'] }} tracked posts sent <strong>{{ number_format($soc['site_sessions']) }}</strong> visits and <strong>{{ number_format($soc['key_events']) }}</strong> key events to the site.</p>
            <ul class="text-sm list-disc ml-5 text-gray-600">@foreach($soc['top_by_site_sessions'] as $p)<li>{{ \Illuminate\Support\Str::limit($p['title'], 80) }}: {{ $p['site_sessions'] }} visits</li>@endforeach</ul>
        @else
            <p class="text-sm text-gray-500">{{ $soc['note'] }}</p>
        @endif
    </section>

    {{-- 4. Money --}}
    <section class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-700 p-5 space-y-3">
        <h2 class="font-semibold text-gray-900 dark:text-slate-100">Money earned</h2>
        <p class="text-xs text-gray-500">The system cannot see affiliate or partner earnings, so this is what you type in from your real statements (GetYourGuide, Awin, Orange Adventures…). Nothing here is estimated. Each currency is kept separate.</p>

        <form method="POST" action="{{ route('impact.revenue.store') }}" class="flex flex-wrap gap-2 items-end text-sm">
            @csrf
            <div><label class="block text-xs text-gray-500">Month</label><input type="month" name="month" required max="{{ now()->format('Y-m') }}" value="{{ old('month') }}" class="border rounded px-2 py-1"></div>
            <div><label class="block text-xs text-gray-500">Source</label><input name="source" required maxlength="60" value="{{ old('source') }}" placeholder="GetYourGuide" class="border rounded px-2 py-1 w-40"></div>
            <div><label class="block text-xs text-gray-500">Amount</label><input name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}" class="border rounded px-2 py-1 w-28"></div>
            <div><label class="block text-xs text-gray-500">Currency</label><input name="currency" required maxlength="3" value="{{ old('currency', 'USD') }}" class="border rounded px-2 py-1 w-20 uppercase"></div>
            <div><label class="block text-xs text-gray-500">Note (optional)</label><input name="note" maxlength="200" value="{{ old('note') }}" class="border rounded px-2 py-1 w-48"></div>
            <button class="px-3 py-1.5 bg-emerald-600 text-white rounded">Add</button>
        </form>

        @if($rev['by_month'])
            <table class="text-sm w-full max-w-md"><thead><tr class="text-left text-gray-500"><th class="py-1">Month</th><th>Total</th></tr></thead><tbody>
                @foreach($rev['by_month'] as $m => $cs)
                    <tr class="border-t border-gray-100"><td class="py-1">{{ $m }}</td><td>@foreach($cs as $c => $amt){{ number_format($amt, 2) }} {{ $c }}@if(!$loop->last) · @endif @endforeach</td></tr>
                @endforeach
            </tbody></table>
            @if($rev['compare'])
                @foreach($rev['compare'] as $c => $cmp)
                    <p class="text-sm text-gray-700 dark:text-slate-300">{{ $c }}: average per month {{ number_format($cmp['before_avg'], 2) }} before ({{ $cmp['months_before'] }} months) vs {{ number_format($cmp['after_avg'], 2) }} after ({{ $cmp['months_after'] }} months){{ $cmp['change_pct'] !== null ? ', ' . ($cmp['change_pct'] > 0 ? '+' : '') . $cmp['change_pct'] . '%' : '' }}.</p>
                @endforeach
            @elseif($r['start'])
                <p class="text-xs text-gray-500">A before/after comparison appears once at least two months are logged on each side of {{ \Illuminate\Support\Carbon::parse($r['start'])->format('Y-m') }}.</p>
            @endif
            <details class="text-xs"><summary class="cursor-pointer text-gray-600">Entries ({{ $rev['entries']->count() }})</summary>
                <ul class="mt-1 space-y-1">@foreach($rev['entries'] as $e)
                    <li class="flex items-center gap-2">{{ $e->month->format('Y-m') }} · {{ $e->source }} · {{ number_format((float) $e->amount, 2) }} {{ $e->currency }}{{ $e->note ? ' · ' . $e->note : '' }}
                        <form method="POST" action="{{ route('impact.revenue.destroy', $e->id) }}" onsubmit="return confirm('Remove this entry?')">@csrf @method('DELETE')<button class="text-red-700">remove</button></form></li>
                @endforeach</ul></details>
        @else
            <p class="text-sm text-gray-500">Nothing logged yet.</p>
        @endif
    </section>
</div>
@endsection
