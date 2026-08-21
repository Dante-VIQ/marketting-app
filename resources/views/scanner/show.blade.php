@extends('layouts.app')

@section('title', 'Page Snapshot')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📄 Page Snapshot</h1>
                <p class="text-gray-600">{{ $snapshot->title ?? 'Untitled' }}</p>
                <div class="flex items-center space-x-2 mt-1">
                    <span class="text-sm text-gray-500">URL:</span>
                    @if($snapshot->status === 'completed')
                        <a href="{{ $snapshot->url }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                            {{ $snapshot->url }} ↗
                        </a>
                    @else
                        <span class="text-gray-400">{{ $snapshot->url }}</span>
                    @endif
                    <button onclick="toggleEditUrl()"
                            class="px-2 py-1 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                        ✏️ Edit
                    </button>
                </div>
            </div>
            <div class="flex space-x-2">
                <form action="{{ route('scanner.rescan', $snapshot->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        🔄 Re-scan
                    </button>
                </form>
                <a href="{{ route('scanner.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    ← Back
                </a>
            </div>
        </div>
    </div>

    <!-- Edit URL Modal -->
    <div id="editUrlModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">✏️ Edit URL</h3>
                <button onclick="toggleEditUrl()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="{{ route('scanner.update-url', $snapshot->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current URL</label>
                    <p class="text-sm text-gray-500 bg-gray-50 p-2 rounded">{{ $snapshot->url }}</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">New URL</label>
                    <input type="url" name="url" value="{{ $snapshot->url }}" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    <p class="text-xs text-gray-500 mt-1">Enter the correct URL for this page.</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleEditUrl()"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        💾 Update URL
                    </button>
                </div>
            </form>
        </div>
    </div>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<!-- Main Content (2/3) -->
<div class="lg:col-span-2 space-y-6">
<!-- Page Metadata -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">📋 Page Metadata</h2>
</div>
<div class="p-6">
<div class="grid grid-cols-2 gap-4">
<div>
<p class="text-sm text-gray-500">Title</p>
<p class="text-sm font-medium text-gray-900">{{ $snapshot->title ?? 'N/A' }}</p>
</div>
<div>
<p class="text-sm text-gray-500">Page Type</p>
<span class="px-2 py-1 text-xs rounded-full
{{ $snapshot->page_type === 'blog' ? 'bg-green-100 text-green-800' : '' }}
{{ $snapshot->page_type === 'service' ? 'bg-blue-100 text-blue-800' : '' }}
{{ $snapshot->page_type === 'contact' ? 'bg-yellow-100 text-yellow-800' : '' }}
{{ $snapshot->page_type === 'home' ? 'bg-purple-100 text-purple-800' : '' }}
">
{{ ucfirst($snapshot->page_type ?? 'Unknown') }}
</span>
</div>
<div>
<p class="text-sm text-gray-500">Word Count</p>
<p class="text-sm font-medium text-gray-900">{{ number_format($snapshot->word_count) }}</p>
</div>
<div>
<p class="text-sm text-gray-500">Readability Score</p>
<p class="text-sm font-medium text-gray-900">{{ $snapshot->readability_score ?? 'N/A' }}</p>
</div>
<div>
<p class="text-sm text-gray-500">Scanned At</p>
<p class="text-sm font-medium text-gray-900">{{ $snapshot->scraped_at ? $snapshot->scraped_at->format('M d, Y H:i') : 'N/A' }}</p>
</div>
<div>
<p class="text-sm text-gray-500">Status</p>
<span class="px-2 py-1 text-xs rounded-full
{{ $snapshot->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
{{ $snapshot->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}
{{ $snapshot->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
">
{{ ucfirst($snapshot->status) }}
</span>
</div>
</div>
</div>
</div>

<!-- SEO Information -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">🔍 SEO Information</h2>
</div>
<div class="p-6 space-y-3">
<div>
<p class="text-sm text-gray-500">Meta Title</p>
<p class="text-sm text-gray-900">{{ $snapshot->meta_title ?? 'Not set' }}</p>
@if($snapshot->meta_title)
<p class="text-xs text-gray-400">Length: {{ strlen($snapshot->meta_title) }} chars (50-60 recommended)</p>
@endif
</div>
<div>
<p class="text-sm text-gray-500">Meta Description</p>
<p class="text-sm text-gray-900">{{ $snapshot->meta_description ?? 'Not set' }}</p>
@if($snapshot->meta_description)
<p class="text-xs text-gray-400">Length: {{ strlen($snapshot->meta_description) }} chars (140-160 recommended)</p>
@endif
</div>
@if($snapshot->canonical_url)
<div>
<p class="text-sm text-gray-500">Canonical URL</p>
<p class="text-sm text-gray-900">{{ $snapshot->canonical_url }}</p>
</div>
@endif
</div>
</div>

<!-- Headings -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">📑 Headings Structure</h2>
</div>
<div class="p-6 space-y-2">
@if($snapshot->headings)
@if(isset($snapshot->headings['h1']) && $snapshot->headings['h1'])
<div class="flex items-start space-x-2">
<span class="text-xs font-bold text-gray-400 w-8">H1</span>
<span class="text-sm font-medium text-gray-900">{{ $snapshot->headings['h1'] }}</span>
</div>
@endif
@if(isset($snapshot->headings['h2']) && !empty($snapshot->headings['h2']))
@foreach($snapshot->headings['h2'] as $heading)
<div class="flex items-start space-x-2">
<span class="text-xs font-bold text-gray-400 w-8">H2</span>
<span class="text-sm text-gray-700">{{ $heading }}</span>
</div>
@endforeach
@endif
@if(isset($snapshot->headings['h3']) && !empty($snapshot->headings['h3']))
@foreach($snapshot->headings['h3'] as $heading)
<div class="flex items-start space-x-2 ml-4">
<span class="text-xs font-bold text-gray-400 w-8">H3</span>
<span class="text-sm text-gray-600">{{ $heading }}</span>
</div>
@endforeach
@endif
@else
<p class="text-gray-500 text-sm">No headings found</p>
@endif
</div>
</div>

<!-- Content Preview -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">📝 Content Preview</h2>
</div>
<div class="p-6">
<div class="prose max-w-none text-sm text-gray-700 max-h-96 overflow-y-auto">
{!! Str::limit(strip_tags($snapshot->content), 1000) !!}
@if(strlen(strip_tags($snapshot->content)) > 1000)
<p class="text-gray-400 text-xs mt-2">... content truncated</p>
@endif
</div>
</div>
</div>
</div>

<!-- Sidebar (1/3) -->
<div class="space-y-6">
<!-- Topics Covered -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">🏷️ Topics Covered</h2>
</div>
<div class="p-6">
@if($snapshot->topics_covered && count($snapshot->topics_covered) > 0)
<div class="flex flex-wrap gap-2">
@foreach($snapshot->topics_covered as $topic)
<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
{{ $topic }}
</span>
@endforeach
</div>
@else
<p class="text-gray-500 text-sm">No topics identified</p>
@endif
</div>
</div>

<!-- Content Gaps -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">⚠️ Content Gaps</h2>
</div>
<div class="p-6">
@if($snapshot->content_gaps && count($snapshot->content_gaps) > 0)
<ul class="space-y-2">
@foreach($snapshot->content_gaps as $gap)
<li class="text-sm text-gray-700 flex items-start space-x-2">
<span class="text-red-500">•</span>
<span>{{ $gap }}</span>
</li>
@endforeach
</ul>
@else
<p class="text-gray-500 text-sm">No major gaps identified 🎉</p>
@endif
</div>
</div>

<!-- Recommendations -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">💡 Recommendations</h2>
</div>
<div class="p-6">
@if($snapshot->recommendations && count($snapshot->recommendations) > 0)
<ul class="space-y-2">
@foreach($snapshot->recommendations as $recommendation)
<li class="text-sm text-gray-700 flex items-start space-x-2">
<span class="text-green-500">✓</span>
<span>{{ $recommendation }}</span>
</li>
@endforeach
</ul>
@else
<p class="text-gray-500 text-sm">No recommendations available</p>
@endif
</div>
</div>

<!-- Related Action -->
@if($snapshot->action)
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">🔗 Related Action</h2>
</div>
<div class="p-6">
<p class="text-sm font-medium text-gray-900">{{ $snapshot->action->title }}</p>
<p class="text-sm text-gray-600 mt-1">{{ $snapshot->action->description }}</p>
<a href="{{ route('actions.queue') }}" class="text-xs text-blue-600 hover:text-blue-800 mt-2 inline-block">
View in Action Queue →
</a>
</div>
</div>
@endif

<!-- Quick Stats -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200">
<h2 class="text-lg font-semibold text-gray-900">📊 Quick Stats</h2>
</div>
<div class="p-6 space-y-2 text-sm">
<div class="flex justify-between">
<span class="text-gray-500">Images</span>
<span class="font-medium">{{ $snapshot->image_count }}</span>
</div>
<div class="flex justify-between">
<span class="text-gray-500">Internal Links</span>
<span class="font-medium">{{ count($snapshot->internal_links ?? []) }}</span>
</div>
<div class="flex justify-between">
<span class="text-gray-500">External Links</span>
<span class="font-medium">{{ count($snapshot->external_links ?? []) }}</span>
</div>
<div class="flex justify-between">
<span class="text-gray-500">Load Time</span>
<span class="font-medium">{{ $snapshot->load_time_ms }}ms</span>
</div>
</div>
</div>
</div>
</div>
</div>
<script>
function toggleEditUrl() {
    const modal = document.getElementById('editUrlModal');
    modal.classList.toggle('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('editUrlModal');
    if (!modal.classList.contains('hidden')) {
        const modalContent = modal.querySelector('.bg-white');
        if (!modalContent.contains(event.target) && !event.target.closest('button')) {
            modal.classList.add('hidden');
        }
    }
});
</script>
@endsection
