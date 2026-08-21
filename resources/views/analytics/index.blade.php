@extends('layouts.app')

@section('title', 'Analytics')

@section('content')


<div class="py-6 px-4 sm:px-6 lg:px-8">

    <div class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📊 Analytics</h1>
                <p class="text-gray-600">View your website and marketing analytics</p>
                <p class="text-sm text-gray-500 mt-1">
                    Last updated: {{ $lastCollection ? $lastCollection->created_at->diffForHumans() : 'Never' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form action="{{ route('analytics.fetch') }}" method="POST" id="fetchAnalyticsForm">
                    @csrf
                    <button type="submit" 
                            id="fetchAnalyticsBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span id="fetchBtnText">Fetch Analytics Now</span>
                    </button>
                </form>
                <a href="{{ route('content.drafts') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    📄 View Drafts
                </a>
            </div>
        </div>
    </div>

    <!-- Loading Status -->
    <div id="fetchStatus" class="hidden mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center space-x-3">
            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
            <span class="text-blue-700" id="fetchStatusText">Fetching analytics data...</span>
        </div>
    </div>

    <!-- Filters -->
    @include('analytics.partials.filters')

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Total Visitors</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($summary['total'] ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Daily Average</p>
            <p class="text-2xl font-bold text-indigo-600">{{ number_format($summary['avg'] ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Peak Day</p>
            <p class="text-2xl font-bold text-purple-600">{{ number_format($summary['max'] ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Lowest Day</p>
            <p class="text-2xl font-bold text-gray-600">{{ number_format($summary['min'] ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Days Tracked</p>
            <p class="text-2xl font-bold text-gray-900">{{ $summary['count'] ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Data Source</p>
            <p class="text-2xl font-bold text-green-600">{{ strtoupper($source) }}</p>
        </div>
    </div>

    <!-- Realtime Activity -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">🔴 Live Activity</h2>
                    <span class="flex items-center space-x-2">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-sm text-gray-500">{{ $realtimeData['total_active_users'] ?? 0 }} active now</span>
                    </span>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="bg-gray-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-blue-600">{{ $realtimeData['total_active_users'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500">Active Users</p>
                    </div>
                    @for($i = 0; $i < 4; $i++)
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <p class="text-2xl font-bold text-gray-600">
                                {{ $realtimeData['per_minute'][$i]['active_users'] ?? 0 }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $i }} min ago</p>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <!-- Historical Data Chart (Simple Table) -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">📈 Historical Data</h2>
                <p class="text-sm text-gray-500">Daily breakdown for the selected period</p>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="pb-2 font-semibold">Date</th>
                                @foreach(array_keys($historicalData[array_key_first($historicalData)] ?? []) as $metric)
                                    <th class="pb-2 text-right font-semibold">{{ ucfirst(str_replace('_', ' ', $metric)) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($historicalData as $date => $metrics)
                                <tr>
                                    <td class="py-2 text-sm font-medium text-gray-700">{{ $date }}</td>
                                    @foreach($metrics as $metric => $value)
                                        <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($value) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column (2/3) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Top Pages -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">📄 Top Pages</h2>
                </div>
                <div class="p-6">
                    @if(!empty($topPages))
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase">
                                    <th class="pb-2 font-semibold">Page</th>
                                    <th class="pb-2 text-right font-semibold">Visitors</th>
                                    <th class="pb-2 text-right font-semibold">% of Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($topPages as $page)
                                    <tr>
                                        <td class="py-2 text-sm text-gray-700">{{ $page['dimension'] ?? '/' }}</td>
                                        <td class="py-2 text-sm text-gray-700 text-right">{{ number_format($page['total_visitors'] ?? 0) }}</td>
                                        <td class="py-2 text-sm text-gray-700 text-right">
                                            @if(($summary['total'] ?? 0) > 0)
                                                {{ round((($page['total_visitors'] ?? 0) / ($summary['total'] ?? 1)) * 100, 1) }}%
                                            @else
                                                0%
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-gray-500 text-center py-4">No page data available for this period.</p>
                    @endif
                </div>
            </div>

            <!-- Channel Breakdown -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">📡 Traffic Channels</h2>
                </div>
                <div class="p-6">
                    @if(!empty($channels))
                        <div class="space-y-3">
                            @foreach($channels as $channel)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-lg">
                                            @if(str_contains($channel['dimension'], 'organic'))
                                                🔍
                                            @elseif(str_contains($channel['dimension'], 'direct'))
                                                🏠
                                            @elseif(str_contains($channel['dimension'], 'referral'))
                                                🔗
                                            @elseif(str_contains($channel['dimension'], 'social'))
                                                📱
                                            @else
                                                📊
                                            @endif
                                        </span>
                                        <span class="font-medium text-gray-700">{{ str_replace('source_', '', $channel['dimension']) }}</span>
                                    </div>
                                    <span class="text-sm text-gray-500">{{ number_format($channel['total_visitors'] ?? 0) }} visitors</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No channel data available for this period.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column (1/3) -->
        <div class="space-y-6">
            <!-- Revenue Leaks -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">💰 Revenue Leaks</h2>
                </div>
                <div class="p-6">
                    @if(!empty($revenueLeaks))
                        <div class="space-y-3">
                            @foreach($revenueLeaks as $leak)
                                <div class="p-3 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-sm font-medium text-red-800">{{ $leak['opportunity_description'] ?? 'Revenue leak detected' }}</p>
                                    <p class="text-sm text-red-600 mt-1">
                                        Estimated Loss: ${{ number_format($leak['estimated_loss'] ?? 0, 2) }}
                                    </p>
                                    <p class="text-xs text-red-500 mt-1">
                                        Detected: {{ isset($leak['detected_date']) ? \Carbon\Carbon::parse($leak['detected_date'])->format('M d, Y') : 'Recent' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No revenue leaks detected. 🎉</p>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">⚡ Quick Actions</h2>
                </div>
                <div class="p-6 space-y-2">
                    <a href="{{ route('actions.queue') }}" 
                       class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                        📋 Review Action Queue
                    </a>
                    <a href="{{ route('seo.index') }}" 
                       class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                        🔍 SEO Assistant
                    </a>
                    <a href="{{ route('campaigns.index') }}" 
                       class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                        📊 Campaign Manager
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('fetchAnalyticsForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('fetchAnalyticsBtn');
        const btnText = document.getElementById('fetchBtnText');
        const statusDiv = document.getElementById('fetchStatus');
        const statusText = document.getElementById('fetchStatusText');
        
        // Disable button and show loading
        btn.disabled = true;
        btnText.textContent = 'Fetching...';
        statusDiv.classList.remove('hidden');
        statusText.textContent = 'Connecting to Google Analytics...';
        statusDiv.className = 'mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg';
        
        // Update status messages
        const messages = [
            'Fetching data from Google Analytics...',
            'Processing visitor data...',
            'Analyzing page views...',
            'Updating dashboard...'
        ];
        
        let msgIndex = 0;
        const msgInterval = setInterval(() => {
            if (msgIndex < messages.length) {
                statusText.textContent = messages[msgIndex];
                msgIndex++;
            }
        }, 2000);
        
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        // Submit the form via AJAX
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        })
        .then(async response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned non-JSON response. Check if the route is correct.');
            }
            
            if (!response.ok) {
                const text = await response.text();
                throw new Error(`Server error (${response.status}): ${text.substring(0, 100)}`);
            }
            
            return response.json();
        })
        .then(data => {
            clearInterval(msgInterval);
            
            if (data.success) {
                statusText.textContent = '✅ ' + data.message;
                statusDiv.className = 'mb-4 p-4 bg-green-50 border border-green-200 rounded-lg';
                
                // Reload the page after 1.5 seconds to show new data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                statusText.textContent = '❌ ' + data.message;
                statusDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
                
                // Re-enable button
                btn.disabled = false;
                btnText.textContent = 'Fetch Analytics Now';
            }
        })
        .catch(error => {
            clearInterval(msgInterval);
            console.error('Fetch error:', error);
            statusText.textContent = '❌ Error: ' + error.message;
            statusDiv.className = 'mb-4 p-4 bg-red-50 border border-red-200 rounded-lg';
            btn.disabled = false;
            btnText.textContent = 'Fetch Analytics Now';
        });
    });
});
</script>
@endpush
@endsection

