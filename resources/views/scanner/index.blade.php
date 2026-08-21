@extends('layouts.app')

@section('title', 'Page Scanner')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
<div class="mb-6">
<div class="flex flex-wrap items-center justify-between gap-4">
<div>
<h1 class="text-2xl font-bold text-gray-900">🔍 Page Scanner</h1>
<p class="text-gray-600">Scan and analyze pages for content insights and SEO recommendations</p>
@if($brand->website_url)
<p class="text-sm text-gray-500 mt-1">Tracking: <span class="font-medium">{{ $brand->website_url }}</span></p>
@endif
</div>
<div class="flex flex-wrap gap-2">
<form action="{{ route('scanner.scan-all') }}" method="POST" id="scanAllForm">
@csrf
<input type="hidden" name="start_url" value="{{ $brand->website_url ?? '' }}">
<input type="hidden" name="depth" value="2">
<button type="submit"
id="scanAllBtn"
class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
</svg>
<span id="scanAllBtnText">Scan All Pages</span>
</button>
</form>
<button onclick="window.location.reload()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
🔄 Refresh
</button>
</div>
</div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
<p class="text-sm text-gray-500">Total Pages</p>
<p class="text-2xl font-bold text-gray-900">{{ $totalPages }}</p>
</div>
<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
<p class="text-sm text-gray-500">Completed</p>
<p class="text-2xl font-bold text-green-600">{{ $completedScans }}</p>
</div>
<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
<p class="text-sm text-gray-500">Pending</p>
<p class="text-2xl font-bold text-yellow-600">{{ $pendingScans }}</p>
</div>
<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
<p class="text-sm text-gray-500">Failed</p>
<p class="text-2xl font-bold text-red-600">{{ $failedScans }}</p>
</div>
<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
<p class="text-sm text-gray-500">With Issues</p>
<p class="text-2xl font-bold text-orange-600">{{ $pagesWithIssues }}</p>
</div>
<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
<p class="text-sm text-gray-500">Need Scan</p>
<p class="text-2xl font-bold text-blue-600">{{ $actionsNeedingScan }}</p>
</div>
</div>

<!-- Page Types Breakdown -->
@if(!empty($pageTypes))
<div class="flex flex-wrap gap-2 mb-6">
@foreach($pageTypes as $type => $count)
<span class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-700">
{{ ucfirst($type) }}: {{ $count }}
</span>
@endforeach
</div>
@endif

<!-- Scanner Status -->
<div id="scanStatus" class="hidden mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
<div class="flex items-center space-x-3">
<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
<span class="text-blue-700" id="scanStatusText">Scanning pages...</span>
</div>
</div>

<!-- Pages List -->
<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
<h2 class="text-lg font-semibold text-gray-900">📄 Scanned Pages</h2>
<div class="flex items-center space-x-2">
<span class="text-sm text-gray-500">{{ $snapshots->count() }} records</span>
<a href="{{ route('scanner.scan') }}" class="text-sm text-blue-600 hover:text-blue-800">+ Add URL</a>
</div>
</div>
<div class="p-6">
@if($snapshots->count() > 0)
<div class="overflow-x-auto">
<table class="min-w-full">
<thead>
<tr class="text-left text-xs text-gray-500 uppercase">
<th class="pb-2 font-semibold">Page</th>
<th class="pb-2 font-semibold">Type</th>
<th class="pb-2 text-right font-semibold">Words</th>
<th class="pb-2 text-right font-semibold">Status</th>
<th class="pb-2 text-right font-semibold">Changed</th>
<th class="pb-2 text-right font-semibold">Scanned</th>
<th class="pb-2 text-right font-semibold">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-100">
@foreach($snapshots as $snapshot)
<tr>
<td class="py-2 text-sm font-medium text-gray-900">
<a href="{{ route('scanner.show', $snapshot->id) }}" class="hover:text-blue-600">
{{ Str::limit($snapshot->title ?? $snapshot->url, 50) }}
</a>
</td>
<td class="py-2">
@php
$typeClasses = match($snapshot->page_type) {
    'blog' => 'bg-green-100 text-green-800',
    'service' => 'bg-blue-100 text-blue-800',
    'contact' => 'bg-yellow-100 text-yellow-800',
    'home' => 'bg-purple-100 text-purple-800',
    'about' => 'bg-indigo-100 text-indigo-800',
    default => 'bg-gray-100 text-gray-800',
};
@endphp
<span class="px-2 py-1 text-xs rounded-full {{ $typeClasses }}">
{{ ucfirst($snapshot->page_type ?? 'Unknown') }}
</span>
</td>
<td class="py-2 text-sm text-gray-700 text-right">{{ number_format($snapshot->word_count ?? 0) }}</td>
<td class="py-2 text-sm text-gray-700 text-right">
@php
$statusClasses = match($snapshot->status) {
    'completed' => 'bg-green-100 text-green-800',
    'pending' => 'bg-yellow-100 text-yellow-800',
    'failed' => 'bg-red-100 text-red-800',
    'processing' => 'bg-blue-100 text-blue-800',
    default => 'bg-gray-100 text-gray-800',
};
@endphp
<span class="px-2 py-1 text-xs rounded-full {{ $statusClasses }}">
{{ ucfirst($snapshot->status) }}
</span>
</td>
<td class="py-2 text-sm text-gray-700 text-right">
@if(isset($snapshot->metadata['content_changed']))
@if($snapshot->metadata['content_changed'])
<span class="text-xs text-yellow-600">🔄 Updated</span>
@else
<span class="text-xs text-gray-400">✅ Unchanged</span>
@endif
@else
<span class="text-xs text-gray-400">New</span>
@endif
</td>
<td class="py-2 text-sm text-gray-700 text-right">
{{ $snapshot->scraped_at ? $snapshot->scraped_at->diffForHumans() : 'Never' }}
</td>
<td class="py-2 text-sm text-gray-700 text-right">
<a href="{{ route('scanner.show', $snapshot->id) }}" class="text-blue-600 hover:text-blue-800">
View →
</a>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>

@if(method_exists($snapshots, 'links'))
<div class="mt-4">
{{ $snapshots->links() }}
</div>
@endif

@else
<div class="text-center py-8">
<div class="text-6xl mb-3">🔍</div>
<h3 class="text-lg font-medium text-gray-900">No Pages Scanned Yet</h3>
<p class="text-sm text-gray-500">Click "Scan All Pages" to start discovering your site's content.</p>
@if(!$brand->website_url)
<p class="text-sm text-yellow-600 mt-2">⚠️ Please set a website URL in Brand Settings first.</p>
@endif
</div>
@endif
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scanAllForm = document.getElementById('scanAllForm');
    if (!scanAllForm) return;

    scanAllForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const btn = document.getElementById('scanAllBtn');
        const btnText = document.getElementById('scanAllBtnText');
        const statusDiv = document.getElementById('scanStatus');
        const statusText = document.getElementById('scanStatusText');

        btn.disabled = true;
        btnText.textContent = 'Scanning...';
    statusDiv.classList.remove('hidden');
    statusText.textContent = 'Starting full site scan...';
    statusDiv.className = 'mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const formData = new FormData(scanAllForm);

    fetch(scanAllForm.action, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: formData,
    })
    .then(async (response) => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || `Server returned HTTP status ${response.status}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            statusText.textContent = '✅ ' + (data.message || 'Scan completed successfully.');
            statusDiv.className = 'mb-4 p-4 bg-green-50 border border-green-200 rounded-lg';
    setTimeout(() => {
        window.location.reload();
    }, 1500);
        } else {
            throw new Error(data.message || 'Scan process encountered an error.');
        }
    })
    .catch(error => {
        statusText.textContent = '❌ Error: ' + error.message;
        statusDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
    btn.disabled = false;
    btnText.textContent = 'Scan All Pages';
    });
    });
});
</script>
@endpush
