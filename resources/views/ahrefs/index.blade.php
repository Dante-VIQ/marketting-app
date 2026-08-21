@extends('layouts.app')

@section('title', 'Ahrefs Dashboard')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
<div class="mb-6">
<div class="flex flex-wrap items-center justify-between gap-4">
<div>
<h1 class="text-2xl font-bold text-gray-900">🔗 Ahrefs SEO Dashboard</h1>
<p class="text-gray-600">Monitor your site's SEO performance, backlinks, and keyword rankings</p>
<div class="flex items-center space-x-2 mt-1">
<span class="text-sm text-gray-500">Tracking:</span>
<span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full font-medium">
{{ $brand->website_url ?? 'No website set' }}
</span>
@if(!$brand->website_url)
<a href="{{ route('brands.index') }}" class="text-xs text-yellow-600 hover:text-yellow-700">
⚠️ Add website URL in Brand Settings
</a>
@endif
</div>
@if($lastCollection)
<p class="text-sm text-gray-500 mt-1">
Last updated: {{ $lastCollection->created_at->diffForHumans() }}
</p>
@endif
</div>
<div class="flex flex-wrap gap-2">
@if($brand->website_url)
<form action="{{ route('ahrefs.collect') }}" method="POST" id="collectAhrefsForm">
@csrf
<button type="submit"
id="collectAhrefsBtn"
class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
</svg>
<span id="collectBtnText">Collect Ahrefs Data</span>
</button>
</form>
@endif
<button onclick="window.location.reload()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
🔄 Refresh
</button>
</div>
</div>
</div>

    <!-- Configuration Status -->
    @if(!$isConfigured)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <p class="text-yellow-700">
                ⚠️ Ahrefs API is not configured. Please add <code class="bg-yellow-100 px-1 py-0.5 rounded">AHREFS_API_TOKEN</code> to your .env file.
            </p>
        </div>
    @endif

    <!-- Site Stats Cards -->
    @if($latestStats)
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Domain Rating</p>
                <p class="text-2xl font-bold {{ $latestStats->domain_rating_color }}">
                    {{ $latestStats->domain_rating ?? 'N/A' }}
                </p>
                <p class="text-xs text-gray-400">Ahrefs DR</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Backlinks</p>
                <p class="text-2xl font-bold text-blue-600">{{ number_format($latestStats->backlinks ?? 0) }}</p>
                <p class="text-xs text-gray-400">Total backlinks</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Referring Domains</p>
                <p class="text-2xl font-bold text-indigo-600">{{ number_format($latestStats->referring_domains ?? 0) }}</p>
                <p class="text-xs text-gray-400">Unique domains</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Organic Keywords</p>
                <p class="text-2xl font-bold text-green-600">{{ number_format($latestStats->organic_keywords ?? 0) }}</p>
                <p class="text-xs text-gray-400">Ranking keywords</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">New Backlinks</p>
                <p class="text-2xl font-bold text-purple-600">{{ number_format($newBacklinksCount ?? 0) }}</p>
                <p class="text-xs text-gray-400">Last 7 days</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <p class="text-sm text-gray-500">Total Keywords</p>
                <p class="text-2xl font-bold text-orange-600">{{ number_format($keywordsCount ?? 0) }}</p>
                <p class="text-xs text-gray-400">Tracked today</p>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Top Keywords -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">📊 Top Keyword Rankings</h2>
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">{{ $topKeywords->count() }}</span>
                    </div>
                    <div class="p-6">
                        @if($topKeywords->count() > 0)
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 uppercase">
                                        <th class="pb-2 font-semibold">Keyword</th>
                                        <th class="pb-2 text-right font-semibold">Position</th>
                                        <th class="pb-2 text-right font-semibold">Volume</th>
                                        <th class="pb-2 text-right font-semibold">Difficulty</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($topKeywords as $keyword)
                                        <tr>
                                            <td class="py-2 text-sm font-medium text-gray-900">{{ $keyword->keyword }}</td>
                                            <td class="py-2 text-sm text-gray-700 text-right">
                                                <span class="px-2 py-1 rounded-full text-xs
                                                    {{ $keyword->position <= 3 ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $keyword->position <= 10 ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $keyword->position > 10 ? 'bg-gray-100 text-gray-800' : '' }}
                                                ">
                                                    #{{ $keyword->position }}
                                                </span>
                                            </td>
                                            <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($keyword->search_volume ?? 0) }}</td>
                                            <td class="py-2 text-sm text-gray-700 text-right">
                                                <span class="px-2 py-1 text-xs rounded-full
                                                    {{ $keyword->difficulty === 'easy' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $keyword->difficulty === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $keyword->difficulty === 'hard' ? 'bg-red-100 text-red-800' : '' }}
                                                ">
                                                    {{ ucfirst($keyword->difficulty ?? 'N/A') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($topKeywords->count() >= 20)
                                <div class="mt-3 text-center">
                                    <span class="text-xs text-gray-400">Showing top 20 of {{ $keywordsCount }} keywords</span>
                                </div>
                            @endif
                        @else
                            <p class="text-gray-500 text-center py-4">No keyword data available. Run the collection to get started.</p>
                        @endif
                    </div>
                </div>

                <!-- Top Referring Domains -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">🌐 Top Referring Domains</h2>
                    </div>
                    <div class="p-6">
                        @if($topDomains->count() > 0)
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 uppercase">
                                        <th class="pb-2 font-semibold">Domain</th>
                                        <th class="pb-2 text-right font-semibold">Links</th>
                                        <th class="pb-2 text-right font-semibold">Domain Rating</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($topDomains as $domain)
                                        <tr>
                                            <td class="py-2 text-sm font-medium text-gray-900">{{ $domain->source_domain }}</td>
                                            <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($domain->total_links) }}</td>
                                            <td class="py-2 text-sm text-gray-700 text-right">
                                                @if($domain->max_dr)
                                                    <span class="px-2 py-1 text-xs rounded-full
                                                        {{ $domain->max_dr >= 80 ? 'bg-green-100 text-green-800' : '' }}
                                                        {{ $domain->max_dr >= 50 ? 'bg-blue-100 text-blue-800' : '' }}
                                                        {{ $domain->max_dr >= 30 ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                        {{ $domain->max_dr < 30 ? 'bg-red-100 text-red-800' : '' }}
                                                    ">
                                                        {{ $domain->max_dr }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-gray-500 text-center py-4">No referring domains data available.</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column (1/3) -->
            <div class="space-y-6">
                <!-- Site Stats History -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">📈 Domain Rating History</h2>
                    </div>
                    <div class="p-6">
                        @if($statsHistory->count() > 0)
                            <div class="space-y-2">
                                @foreach($statsHistory->take(10) as $stat)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">{{ $stat->tracked_date->format('M d') }}</span>
                                        <span class="font-medium {{ $stat->domain_rating_color }}">
                                            {{ $stat->domain_rating ?? 'N/A' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            @if($statsHistory->count() > 10)
                                <p class="text-xs text-gray-400 mt-2">Showing last 10 of {{ $statsHistory->count() }} records</p>
                            @endif
                        @else
                            <p class="text-gray-500 text-center py-4">No history data available.</p>
                        @endif
                    </div>
                </div>

                <!-- Recent Backlinks -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">🔗 Recent Backlinks</h2>
                        <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">{{ $recentBacklinks->count() }}</span>
                    </div>
                    <div class="p-6 max-h-80 overflow-y-auto">
                        @if($recentBacklinks->count() > 0)
                            <div class="space-y-3">
                                @foreach($recentBacklinks as $backlink)
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                        <p class="text-sm font-medium text-gray-900">
                                            <a href="{{ $backlink->source_url }}" target="_blank" class="hover:text-blue-600">
                                                {{ $backlink->source_domain }}
                                            </a>
                                        </p>
                                        @if($backlink->anchor_text)
                                            <p class="text-xs text-gray-600 mt-1">"{{ Str::limit($backlink->anchor_text, 60) }}"</p>
                                        @endif
                                        <div class="flex items-center space-x-2 mt-1">
                                            <span class="text-xs px-2 py-0.5 rounded-full
                                                {{ $backlink->is_follow ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}
                                            ">
                                                {{ $backlink->is_follow ? 'Follow' : 'Nofollow' }}
                                            </span>
                                            @if($backlink->source_domain_rating)
                                                <span class="text-xs text-gray-500">DR: {{ $backlink->source_domain_rating }}</span>
                                            @endif
                                            <span class="text-xs text-gray-400">{{ $backlink->last_seen_at ? $backlink->last_seen_at->diffForHumans() : 'New' }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No recent backlinks.</p>
                        @endif
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">⚡ Quick Actions</h2>
                    </div>
                    <div class="p-6 space-y-2">
                        <a href="{{ route('seo.index') }}" 
                           class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            🔍 View SEO Dashboard
                        </a>
                        <a href="{{ route('analytics.index') }}" 
                           class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            📊 View Analytics
                        </a>
                        <a href="{{ route('actions.queue') }}" 
                           class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            📋 Review Action Queue
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-12 text-center">
                <div class="text-6xl mb-4">🔗</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Ahrefs Data Available</h3>
                <p class="text-gray-500">Click the "Collect Ahrefs Data" button to fetch your SEO metrics.</p>
                <form action="{{ route('ahrefs.collect') }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        📥 Collect Ahrefs Data
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('collectAhrefsForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('collectAhrefsBtn');
        const btnText = document.getElementById('collectBtnText');
        
        btn.disabled = true;
        btnText.textContent = 'Collecting...';
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                btnText.textContent = '✅ Done! Refreshing...';
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                btnText.textContent = '❌ Failed';
                btn.disabled = false;
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            btnText.textContent = '❌ Error';
            btn.disabled = false;
            alert('Error: ' + error.message);
        });
    });
});
</script>
@endpush
