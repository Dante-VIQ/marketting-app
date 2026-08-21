@extends('layouts.app')

@section('title', 'AI Brief Detail')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📋 AI Brief</h1>
                <p class="text-gray-600">
                    {{ \Carbon\Carbon::parse($brief->brief_date)->format('F j, Y') }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('briefs.index') }}" 
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    ← Back to Briefs
                </a>
                <a href="{{ route('actions.queue') }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    View Actions →
                </a>
            </div>
        </div>
    </div>

    <!-- Brief Metadata -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">AI Provider</p>
            <p class="font-medium text-gray-900">{{ $brief->ai_provider ?? 'Unknown' }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Model</p>
            <p class="font-medium text-gray-900">{{ $brief->model_used ?? 'Unknown' }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Tokens Used</p>
            <p class="font-medium text-gray-900">{{ number_format($brief->tokens_used ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Response Time</p>
            <p class="font-medium text-gray-900">{{ round(($brief->response_time_ms ?? 0) / 1000, 2) }}s</p>
        </div>
    </div>

    <!-- Strategic Diagnosis -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📊 Strategic Diagnosis</h2>
        </div>
        <div class="p-6">
            <div class="prose max-w-none">
                <p class="text-gray-700 whitespace-pre-wrap">{{ $brief->strategic_diagnosis }}</p>
            </div>
            <div class="mt-4 flex flex-wrap gap-4">
                @if($brief->estimated_revenue_impact)
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <p class="text-sm text-gray-500">Estimated Revenue Impact</p>
                        <p class="text-lg font-bold text-green-700">${{ number_format($brief->estimated_revenue_impact, 2) }}</p>
                    </div>
                @endif
                @if($brief->confidence_score)
                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-gray-500">Confidence Score</p>
                        <p class="text-lg font-bold text-blue-700">{{ $brief->confidence_score }}%</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">🎯 Actions ({{ $brief->actions()->count() }})</h2>
            <a href="{{ route('actions.queue') }}" class="text-sm text-blue-600 hover:text-blue-800">
                View in Action Queue →
            </a>
        </div>
        <div class="p-6">
            @if($brief->actions()->count() > 0)
                <div class="space-y-4">
                    @foreach($brief->actions as $action)
                        <div class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $action->category === 'seo' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $action->category === 'content' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $action->category === 'social' ? 'bg-purple-100 text-purple-800' : '' }}
                                            {{ $action->category === 'email' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $action->category === 'campaign' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $action->category === 'strategy' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                            {{ $action->category === 'analytics' ? 'bg-gray-100 text-gray-800' : '' }}
                                        ">
                                            {{ ucfirst($action->category) }}
                                        </span>
                                        <span class="text-xs text-gray-500">Priority: {{ $action->priority }}/5</span>
                                        <span class="text-xs px-2 py-1 rounded-full 
                                            {{ $action->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $action->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $action->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $action->status === 'content_generated' ? 'bg-blue-100 text-blue-800' : '' }}
                                        ">
                                            {{ ucfirst(str_replace('_', ' ', $action->status)) }}
                                        </span>
                                        @if($action->estimated_impact)
                                            <span class="text-xs text-green-600">Impact: ${{ number_format($action->estimated_impact, 2) }}</span>
                                        @endif
                                    </div>
                                    <h3 class="font-medium text-gray-900">{{ $action->title }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $action->description }}</p>
                                    @if($action->suggested_content)
                                        <div class="mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-700">
                                            <strong>Suggested:</strong> {{ $action->suggested_content }}
                                        </div>
                                    @endif
                                    @if($action->target_url)
                                        <p class="text-xs text-blue-600 mt-1">Target: {{ $action->target_url }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-center py-4">No actions found for this brief.</p>
            @endif
        </div>
    </div>
</div>
@endsection