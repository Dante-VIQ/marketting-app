@extends('layouts.app')

@section('title', 'SEO Assistant')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🔍 SEO Assistant</h1>
            <p class="text-gray-600">Monitor and improve your search engine optimization</p>
        </div>
        <div class="flex space-x-2">
            <form action="{{ route('seo.run-checks') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    🔄 Run SEO Checks
                </button>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Total Issues</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total_issues'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Open Issues</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['open_issues'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Resolved</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['resolved_issues'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Critical Issues</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['critical_issues'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Avg. Keyword Position</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['avg_position'] }}</p>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Open Issues -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">🚨 Open Issues</h2>
                    <span class="text-xs px-2 py-1 bg-red-100 text-red-800 rounded-full">{{ $issues->count() }}</span>
                </div>
                <div class="p-6">
                    @if($issues->count() > 0)
                        <div class="space-y-3">
                            @foreach($issues as $issue)
                                <div class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                    {{ $issue->severity === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                                                    {{ $issue->severity === 'high' ? 'bg-orange-100 text-orange-800' : '' }}
                                                    {{ $issue->severity === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $issue->severity === 'low' ? 'bg-blue-100 text-blue-800' : '' }}
                                                ">
                                                    {{ ucfirst($issue->severity) }}
                                                </span>
                                                <span class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $issue->type)) }}</span>
                                                <span class="text-xs text-gray-400">Page: {{ $issue->page_url }}</span>
                                            </div>
                                            <p class="text-sm text-gray-700">{{ $issue->description }}</p>
                                            <p class="text-sm text-blue-600 mt-1">💡 {{ $issue->recommendation }}</p>
                                        </div>
                                        <div class="flex flex-col space-y-2 ml-4">
                                            <form action="{{ route('seo.issue.resolve', $issue->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" 
                                                        class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700 transition"
                                                        onclick="return confirm('Mark this issue as resolved?')">
                                                    ✅ Resolve
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="text-6xl mb-3">🎉</div>
                            <h3 class="text-lg font-medium text-gray-900">No Open Issues</h3>
                            <p class="text-sm text-gray-500">Your SEO is looking great!</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Keyword Rankings -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">📊 Keyword Rankings</h2>
                    <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">{{ $keywords->count() }}</span>
                </div>
                <div class="p-6">
                    @if($keywords->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs text-gray-500 uppercase">
                                        <th class="pb-2 font-semibold">Keyword</th>
                                        <th class="pb-2 text-right font-semibold">Position</th>
                                        <th class="pb-2 text-right font-semibold">Change</th>
                                        <th class="pb-2 text-right font-semibold">Volume</th>
                                        <th class="pb-2 text-right font-semibold">Difficulty</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($keywords as $keyword)
                                        <tr>
                                            <td class="py-2 text-sm font-medium text-gray-700">{{ $keyword->keyword }}</td>
                                            <td class="py-2 text-sm text-gray-700 text-right">{{ $keyword->position }}</td>
                                            <td class="py-2 text-sm text-right">
                                                @if($keyword->previous_position)
                                                    @php
                                                        $diff = $keyword->previous_position - $keyword->position;
                                                    @endphp
                                                    @if($diff > 0)
                                                        <span class="text-green-600">↑ {{ $diff }}</span>
                                                    @elseif($diff < 0)
                                                        <span class="text-red-600">↓ {{ abs($diff) }}</span>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">New</span>
                                                @endif
                                            </td>
                                            <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($keyword->search_volume ?? 0) }}</td>
                                            <td class="py-2 text-sm text-right">
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
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No keyword rankings available yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column (1/3) -->
        <div class="space-y-6">
            <!-- SEO Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">📋 SEO Actions</h2>
                    <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">{{ $seoActions->count() }}</span>
                </div>
                <div class="p-6">
                    @if($seoActions->count() > 0)
                        <div class="space-y-2">
                            @foreach($seoActions as $action)
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-sm font-medium text-gray-900">{{ $action->title }}</p>
                                    <p class="text-xs text-gray-600 mt-1">{{ $action->description }}</p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="text-xs text-gray-500">Priority: {{ $action->priority }}/5</span>
                                        <span class="text-xs text-green-600">Impact: ${{ number_format($action->estimated_impact ?? 0, 2) }}</span>
                                    </div>
                                    <a href="{{ url('/actions/queue') }}" class="text-xs text-blue-600 hover:text-blue-800">View in Queue →</a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No pending SEO actions.</p>
                    @endif
                </div>
            </div>

            <!-- Recent Resolved -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">✅ Recent Resolved</h2>
                </div>
                <div class="p-6">
                    @if($resolvedIssues->count() > 0)
                        <div class="space-y-2">
                            @foreach($resolvedIssues as $issue)
                                <div class="flex items-center justify-between p-2 bg-green-50 rounded-lg">
                                    <div>
                                        <p class="text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $issue->type)) }}</p>
                                        <p class="text-xs text-gray-500">{{ $issue->page_url }}</p>
                                    </div>
                                    <span class="text-xs text-green-600">{{ $issue->resolved_at->diffForHumans() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No resolved issues yet.</p>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">⚡ Quick Actions</h2>
                </div>
                <div class="p-6 space-y-2">
                    <a href="{{ url('/actions/queue') }}" 
                       class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                        📋 View Action Queue
                    </a>
                    <a href="{{ url('/analytics/index') }}" 
                       class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                        📊 View Analytics
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SEO Report Section -->
    <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📄 SEO Report</h2>
        </div>
        <div class="p-6">
            <div class="prose max-w-none">
                <pre class="text-sm bg-gray-50 p-4 rounded-lg whitespace-pre-wrap">{{ $report }}</pre>
            </div>
        </div>
    </div>
</div>
@endsection