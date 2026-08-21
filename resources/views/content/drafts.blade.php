@extends('layouts.app')

@section('title', 'Content Drafts')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📄 Content Drafts</h1>
                <p class="text-gray-600">Review and publish AI-generated content</p>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $approvedActionsWithoutDrafts ?? 0 }} approved action(s) waiting for content generation
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form action="{{ route('content.generate-all') }}" method="POST" id="generateContentForm">
                    @csrf
                    <button type="submit" 
                            id="generateContentBtn"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span id="generateBtnText">⚡ Generate All Content</span>
                    </button>
                </form>
                <button onclick="window.location.reload()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    🔄 Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Total Drafts</p>
            <p class="text-2xl font-bold text-gray-900">{{ $totalDrafts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Pending Review</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $pendingDrafts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Approved</p>
            <p class="text-2xl font-bold text-green-600">{{ $approvedDrafts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Published</p>
            <p class="text-2xl font-bold text-purple-600">{{ $publishedDrafts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Awaiting Content</p>
            <p class="text-2xl font-bold text-blue-600">{{ $approvedActionsWithoutDrafts ?? 0 }}</p>
        </div>
    </div>

    <!-- Loading/Status Message -->
    <div id="generateStatus" class="hidden mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center space-x-3">
            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
            <span class="text-blue-700" id="generateStatusText">Generating content drafts...</span>
        </div>
    </div>

    <!-- Info Banner -->
    @if(($approvedActionsWithoutDrafts ?? 0) > 0)
        <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <p class="text-sm text-blue-700">
                💡 <strong>{{ $approvedActionsWithoutDrafts }}</strong> approved action(s) are waiting for content generation.
                Click the "Generate All Content" button above to create drafts.
            </p>
        </div>
    @endif

    <!-- Draft Manager -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            @livewire('draft-manager')
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('generateContentForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('generateContentBtn');
        const btnText = document.getElementById('generateBtnText');
        const statusDiv = document.getElementById('generateStatus');
        const statusText = document.getElementById('generateStatusText');
        
        // Disable button and show loading
        btn.disabled = true;
        btnText.textContent = 'Queuing...';
        statusDiv.classList.remove('hidden');
        statusText.textContent = 'Starting content generation queue...';
        statusDiv.className = 'mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg';
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        // Submit via AJAX
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response.');
            }
            if (!response.ok) {
                throw new Error(`Server error (${response.status})`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                statusText.textContent = '✅ ' + data.message;
                statusDiv.className = 'mb-4 p-4 bg-green-50 border border-green-200 rounded-lg';
                
                // Start polling for completion
                if (data.dispatched > 0) {
                    pollQueueStatus();
                }
            } else {
                statusText.textContent = '❌ ' + data.message;
                statusDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
                btn.disabled = false;
                btnText.textContent = '⚡ Generate All Content';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            statusText.textContent = '❌ Error: ' + error.message;
            statusDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
            btn.disabled = false;
            btnText.textContent = '⚡ Generate All Content';
        });
    });
    
    function pollQueueStatus() {
        const statusText = document.getElementById('generateStatusText');
        let attempts = 0;
        const maxAttempts = 30; // 30 * 2 seconds = 60 seconds max
        
        const interval = setInterval(() => {
            attempts++;
            
            fetch('/content/queue-status', {
                headers: {
                    'Accept': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                const pending = data.pending || 0;
                const processing = data.processing || 0;
                
                statusText.textContent = `⏳ Processing: ${pending} pending, ${processing} in progress, ${data.completed || 0} completed`;
                
                if (pending === 0 && attempts > 2) {
                    clearInterval(interval);
                    statusText.textContent = '✅ All content generation complete! Refreshing...';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
                
                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    statusText.textContent = '⏳ Content generation is still running. Refresh the page to check progress.';
                }
            })
            .catch(() => {
                // Silent fail on polling errors
            });
        }, 3000);
    }
});
</script>
@endpush