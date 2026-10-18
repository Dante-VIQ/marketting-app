@extends('layouts.app')

@section('title', 'Competitors')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">🔭 Competitors</h1>
        <p class="text-gray-600">Free scan of each competitor's public homepage, sitemap and blog feed, every Monday. See how often they publish and what topics you have not covered.</p>
    </div>

    @if(session('message'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Add --}}
    <form method="POST" action="{{ route('competitors.store') }}" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="block text-xs text-gray-500 mb-1">Website</label>
            <input name="domain" value="{{ old('domain') }}" required placeholder="competitor.com" class="text-sm border-gray-300 rounded-lg w-64">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Name (optional)</label>
            <input name="name" value="{{ old('name') }}" placeholder="Acme Safaris" class="text-sm border-gray-300 rounded-lg w-52">
        </div>
        <button class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Track competitor</button>
    </form>

    {{-- Topic gaps --}}
    @if(!empty($data['topic_gaps']))
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">🕳️ Topics they cover that you don't</h2>
                <p class="text-xs text-gray-500">Recurring words in competitor post titles that appear in none of your post titles or tracked keywords. Ideas to check, not proof of demand.</p>
            </div>
            <div class="p-6 flex flex-wrap gap-2">
                @foreach($data['topic_gaps'] as $g)
                    <span class="px-3 py-1 rounded-full text-sm bg-blue-50 text-blue-800 border border-blue-100">
                        {{ $g['term'] }} <span class="text-xs text-blue-500">· {{ $g['competitors'] }} {{ Str::plural('site', $g['competitors']) }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Competitors --}}
    <div class="space-y-4">
        @forelse($data['competitors'] as $c)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $c['name'] }}
                            <a href="https://{{ $c['domain'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-normal text-blue-600 hover:text-blue-800">{{ $c['domain'] }} ↗</a>
                        </h3>
                        @if($c['homepage_title'])<p class="text-sm text-gray-600">{{ $c['homepage_title'] }}</p>@endif
                        <p class="text-xs text-gray-400 mt-1">
                            @if($c['scanned_on']) Last scan {{ $c['scanned_on'] }} @else Not scanned yet @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('competitors.scan', $c['id']) }}">
                            @csrf
                            <button class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Scan now</button>
                        </form>
                        <form method="POST" action="{{ route('competitors.destroy', $c['id']) }}" onsubmit="return confirm('Stop tracking {{ $c['domain'] }}?')">
                            @csrf @method('DELETE')
                            <button class="px-3 py-1.5 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Remove</button>
                        </form>
                    </div>
                </div>

                @if($c['status'] === 'error')
                    <p class="mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">{{ $c['error'] }}</p>
                @elseif($c['status'] === 'ok')
                    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Posts in last 30 days</p>
                            <p class="text-xl font-bold text-gray-900">{{ $c['posts_last_30d'] ?? '—' }}</p>
                            <p class="text-xs text-gray-400">
                                @if($c['cadence_basis']) from their {{ $c['cadence_basis'] }} @else no feed or dated sitemap found @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Pages in sitemap</p>
                            <p class="text-xl font-bold text-gray-900">{{ $c['sitemap_url_count'] !== null ? number_format($c['sitemap_url_count']) : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Latest post</p>
                            <p class="text-xl font-bold text-gray-900">{{ $c['latest_post_at'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Recurring topics</p>
                            <p class="text-sm text-gray-800">
                                @forelse($c['top_terms'] as $term => $count){{ $term }}@if(!$loop->last), @endif @empty — @endforelse
                            </p>
                        </div>
                    </div>
                    @if(!empty($c['recent_posts']))
                        <ul class="mt-4 text-sm text-gray-700 list-disc list-inside">
                            @foreach($c['recent_posts'] as $p)
                                <li>
                                    @if(!empty($p['url']) && preg_match('#^https?://#i', $p['url']))
                                        <a href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer" class="hover:text-blue-600">{{ $p['title'] }}</a>
                                    @else {{ $p['title'] }} @endif
                                    @if(!empty($p['date']))<span class="text-xs text-gray-400">· {{ \Illuminate\Support\Str::before($p['date'], 'T') }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        @empty
            <div class="bg-white rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
                No competitors yet. Add the websites of the businesses you compete with above.
            </div>
        @endforelse
    </div>
</div>
@endsection
