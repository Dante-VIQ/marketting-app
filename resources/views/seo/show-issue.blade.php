@extends('layouts.app')

@section('title', 'SEO Issue Details')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">🔍 SEO Issue Details</h1>
                <p class="text-gray-600">Review and resolve SEO issues</p>
            </div>
            <a href="{{ route('seo.index') }}" 
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                ← Back to SEO Dashboard
            </a>
        </div>
    </div>

    <!-- Issue Details -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Issue Details</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Type</label>
                            <p class="text-gray-900">{{ ucfirst(str_replace('_', ' ', $issue->type)) }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Page URL</label>
                            <p class="text-gray-900">
                                <a href="{{ $issue->page_url }}" target="_blank" 
                                   class="text-blue-600 hover:text-blue-800">
                                    {{ $issue->page_url }}
                                    <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Severity</label>
                            <span class="px-3 py-1 text-sm rounded-full 
                                {{ $issue->severity === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $issue->severity === 'high' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $issue->severity === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $issue->severity === 'low' ? 'bg-blue-100 text-blue-800' : '' }}
                            ">
                                {{ ucfirst($issue->severity) }}
                            </span>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <span class="px-3 py-1 text-sm rounded-full 
                                {{ $issue->status === 'open' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $issue->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $issue->status === 'resolved' ? 'bg-green-100 text-green-800' : '' }}
                            ">
                                {{ ucfirst($issue->status) }}
                            </span>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Description</label>
                            <p class="text-gray-900 bg-gray-50 p-3 rounded-lg">{{ $issue->description }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Recommendation</label>
                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                <p class="text-blue-700">💡 {{ $issue->recommendation }}</p>
                            </div>
                        </div>
                        @if($issue->resolved_at)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Resolved At</label>
                                <p class="text-gray-900">{{ $issue->resolved_at->format('F j, Y, g:i A') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Related Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">📋 Related Actions</h2>
                </div>
                <div class="p-6">
                    @php
                        $relatedActions = App\Models\AiAction::where('brand_id', $issue->brand_id)
                            ->where('category', 'seo')
                            ->where('target_url', $issue->page_url)
                            ->get();
                    @endphp
                    @if($relatedActions->count() > 0)
                        <div class="space-y-2">
                            @foreach($relatedActions as $action)
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="text-sm font-medium text-gray-900">{{ $action->title }}</p>
                                    <p class="text-xs text-gray-600">{{ $action->description }}</p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="text-xs px-2 py-1 rounded-full 
                                            {{ $action->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $action->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $action->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                        ">
                                            {{ ucfirst($action->status) }}
                                        </span>
                                        <span class="text-xs text-gray-500">Priority: {{ $action->priority }}/5</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No related actions found.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar (1/3) -->
        <div class="space-y-6">
            <!-- Actions Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Actions</h2>
                </div>
                <div class="p-6 space-y-3">
                    @if($issue->status !== 'resolved')
                        <form action="{{ route('seo.issue.resolve', $issue->id) }}" method="POST">
                            @csrf
                            <button type="submit" 
                                    class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                                    onclick="return confirm('Mark this issue as resolved?')">
                                ✅ Mark as Resolved
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('actions.queue') }}" 
                       class="block w-full text-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        📋 View in Action Queue
                    </a>

                    <a href="{{ route('seo.index') }}" 
                       class="block w-full text-center px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        🔙 Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Metadata Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">📄 Metadata</h2>
                </div>
                <div class="p-6 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Created</span>
                        <span class="text-gray-700">{{ $issue->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Updated</span>
                        <span class="text-gray-700">{{ $issue->updated_at->diffForHumans() }}</span>
                    </div>
                    @if($issue->resolved_at)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Resolved</span>
                            <span class="text-gray-700">{{ $issue->resolved_at->diffForHumans() }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-500">ID</span>
                        <span class="text-gray-700">#{{ $issue->id }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection