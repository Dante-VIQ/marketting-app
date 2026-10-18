@extends('layouts.app')

@section('title', 'Social Listening')

@php
    $sourceLabels = ['google_news' => 'Google News', 'reddit' => 'Reddit', 'hackernews' => 'Hacker News'];
    $state = $data['state'];
    $safeUrl = fn ($u) => is_string($u) && preg_match('#^https?://#i', $u) ? $u : '#';
@endphp

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">👂 Social Listening</h1>
            <p class="text-gray-600">Who is talking about {{ $brand->name }}, your competitors and your topics, across Google News, Reddit and Hacker News. Free, checked daily.</p>
        </div>
        <form method="POST" action="{{ route('listening.check') }}">
            @csrf
            <button class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Check now</button>
        </form>
    </div>

    @if(session('message'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Source health --}}
    @if(!empty($data['sources']))
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="text-gray-500">Last check{{ $data['checked_on'] ? ' ' . $data['checked_on'] : '' }}:</span>
            @foreach($data['sources'] as $src => $st)
                <span class="px-2 py-1 rounded-full {{ $st === 'ok' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $sourceLabels[$src] ?? $src }}: {{ $st === 'ok' ? 'worked' : ($st === 'blocked' ? 'blocked us' : 'unavailable') }}
                </span>
            @endforeach
            @if(in_array('blocked', $data['sources'], true))
                <span class="text-gray-400">(Reddit often blocks server traffic; the other sources still work.)</span>
            @endif
        </div>
    @endif

    {{-- Starting points --}}
    @if($state === 'never_checked')
        <div class="p-5 rounded-lg bg-blue-50 border border-blue-200">
            <h2 class="font-semibold text-blue-900">Ready to listen</h2>
            <p class="text-sm text-blue-800 mt-1">We will look for the terms below. Press <strong>Check now</strong> to see what is out there today. Daily checks run automatically from tomorrow.</p>
        </div>
    @elseif($state === 'no_mentions_yet')
        <div class="p-5 rounded-lg bg-amber-50 border border-amber-200">
            <h2 class="font-semibold text-amber-900">No mentions found yet. That is a normal starting point.</h2>
            <p class="text-sm text-amber-800 mt-1">
                We checked on {{ $data['checked_on'] }} and found nothing public about your searches in the last 30 days.
                This is your baseline: trends and comparisons start once the first mentions appear, and nothing here counts as a drop.
            </p>
        </div>
    @endif

    @if(!empty($data['suggestions']))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900">Where to start</h2>
            <ul class="text-sm text-gray-700 list-disc list-inside space-y-1">
                @foreach($data['suggestions']['tips'] as $tip)<li>{{ $tip }}</li>@endforeach
            </ul>
            @if(!empty($data['suggestions']['topics']))
                <div>
                    <p class="text-sm text-gray-600 mb-2">Topics from your own keyword rankings. One click to start listening:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($data['suggestions']['topics'] as $topic)
                            <form method="POST" action="{{ route('listening.store') }}">
                                @csrf
                                <input type="hidden" name="term" value="{{ $topic }}">
                                <input type="hidden" name="kind" value="topic">
                                <button class="px-3 py-1 rounded-full text-sm bg-blue-50 text-blue-800 border border-blue-100 hover:bg-blue-100">+ {{ $topic }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Headline numbers --}}
    @if($state === 'active')
        @php $b = $data['brand']; $sent = $b['sentiment_30d']; @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Brand mentions, 7 days</p>
                <p class="text-2xl font-bold text-gray-900">{{ $b['last_7d'] }}</p>
                <p class="text-xs text-gray-400">
                    @if($b['change_pct'] === null) no earlier week to compare
                    @else {{ $b['change_pct'] >= 0 ? '▲' : '▼' }} {{ abs($b['change_pct']) }}% vs previous 7 days @endif
                </p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Brand mentions, 30 days</p>
                <p class="text-2xl font-bold text-gray-900">{{ $b['total_30d'] }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 md:col-span-2">
                <p class="text-sm text-gray-500">Tone of brand mentions (rough, word-based)</p>
                @if(array_sum($sent) > 0)
                    <p class="text-sm mt-1">
                        <span class="text-green-700">{{ $sent['positive'] ?? 0 }} positive</span> ·
                        <span class="text-gray-700">{{ $sent['neutral'] ?? 0 }} neutral</span> ·
                        <span class="text-red-700">{{ $sent['negative'] ?? 0 }} negative</span>
                    </p>
                @else
                    <p class="text-sm text-gray-400 mt-1">Not enough brand mentions yet.</p>
                @endif
            </div>
        </div>

        @if(count($data['share_of_voice']) > 1)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Share of voice (30 days)</h2></div>
                <div class="p-6 space-y-2">
                    @php $max = max(array_column($data['share_of_voice'], 'mentions')) ?: 1; @endphp
                    @foreach($data['share_of_voice'] as $row)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-40 truncate {{ $row['kind'] === 'brand' ? 'font-semibold text-gray-900' : 'text-gray-600' }}">{{ $row['term'] }}</span>
                            <div class="flex-1 bg-gray-100 rounded h-3"><div class="h-3 rounded {{ $row['kind'] === 'brand' ? 'bg-emerald-500' : 'bg-gray-400' }}" style="width: {{ round($row['mentions'] / $max * 100) }}%"></div></div>
                            <span class="w-8 text-right text-gray-700">{{ $row['mentions'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200"><h2 class="text-lg font-semibold text-gray-900">Latest brand mentions</h2></div>
            <div class="p-6 divide-y divide-gray-100">
                @forelse($data['recent'] as $m)
                    <div class="py-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ $safeUrl($m['url']) }}" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-900 hover:text-blue-600">{{ $m['title'] }}</a>
                            <p class="text-xs text-gray-500">{{ $sourceLabels[$m['source']] ?? $m['source'] }}@if($m['publisher']) · {{ $m['publisher'] }}@endif @if($m['date']) · {{ $m['date'] }}@endif
                                @if($m['comments']) · {{ $m['comments'] }} comments @endif</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($m['sentiment'])
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $m['sentiment'] === 'positive' ? 'bg-green-100 text-green-800' : ($m['sentiment'] === 'negative' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700') }}">{{ $m['sentiment'] }}</span>
                            @endif
                            <form method="POST" action="{{ route('listening.dismiss', $m['id']) }}">@csrf
                                <button class="text-xs text-gray-400 hover:text-gray-600" title="Hide this mention">Hide</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No mentions of your brand yet. Competitor and topic results are below.</p>
                @endforelse
            </div>
        </div>

        @if(!empty($data['conversations']))
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Conversations worth joining</h2>
                    <p class="text-xs text-gray-500">Questions and busy threads about your topics.</p>
                </div>
                <div class="p-6 divide-y divide-gray-100">
                    @foreach($data['conversations'] as $m)
                        <div class="py-2">
                            <a href="{{ $safeUrl($m['url']) }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-gray-900 hover:text-blue-600">{{ $m['title'] }}</a>
                            <p class="text-xs text-gray-500">{{ $sourceLabels[$m['source']] ?? $m['source'] }}@if($m['publisher']) · {{ $m['publisher'] }}@endif · topic: {{ $m['term'] }}@if($m['comments']) · {{ $m['comments'] }} comments @endif</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- What we listen for --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">What we listen for</h2>
            <p class="text-xs text-gray-500">Brand terms: your name and website. Competitors come from the Competitors page. Topics are subjects your customers discuss.</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex flex-wrap gap-2">
                @forelse($data['queries'] as $q)
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm border
                        {{ $q['kind'] === 'brand' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : ($q['kind'] === 'competitor' ? 'bg-gray-50 border-gray-200 text-gray-700' : 'bg-blue-50 border-blue-200 text-blue-800') }}">
                        <span class="text-xs uppercase opacity-60">{{ $q['kind'] }}</span> {{ $q['term'] }}
                        @if($q['kind'] !== 'competitor')
                            <form method="POST" action="{{ route('listening.destroy', $q['id']) }}" onsubmit="return confirm('Stop listening for this?')">@csrf @method('DELETE')
                                <button class="opacity-50 hover:opacity-100" title="Remove">✕</button>
                            </form>
                        @endif
                    </span>
                @empty
                    <p class="text-sm text-gray-500">Nothing yet. Add a brand term or topic below.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('listening.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Add a search</label>
                    <input name="term" required minlength="2" maxlength="120" placeholder="e.g. Maasai Mara safari" class="text-sm border-gray-300 rounded-lg w-72">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                    <select name="kind" class="text-sm border-gray-300 rounded-lg">
                        <option value="topic">Topic</option>
                        <option value="brand">Brand term (name, spelling, product)</option>
                    </select>
                </div>
                <button class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Add</button>
            </form>
        </div>
    </div>
</div>
@endsection
