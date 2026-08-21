@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <!-- Welcome Section -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Good Morning, {{ auth()->user()->name }}! 👋</h1>
            <p class="text-gray-600">{{ $activeBrand->name }} · {{ ucfirst($activeBrand->domain_type) }}</p>
            <p class="text-sm text-gray-500 mt-1">
                Last updated: {{ now()->format('F j, Y, g:i A') }}
            </p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <!-- Visitors -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Visitors (7 days)</p>
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($dashboardData['visitors'] ?? 0) }}</p>
                    </div>
                    <div class="p-3 bg-blue-50 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Leads -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Leads (7 days)</p>
                        <p class="text-2xl font-bold text-green-600">{{ number_format($dashboardData['leads'] ?? 0) }}</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Affiliate Stats -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Affiliate Revenue (30 days)</p>
                        <p class="text-2xl font-bold text-yellow-600">${{ number_format($affiliateRevenue ?? 0, 2) }}</p>
                    </div>
                    <div class="p-3 bg-yellow-50 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Social Reach -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Social Reach</p>
                        <p class="text-2xl font-bold text-purple-600">
                            {{ number_format($dashboardData['social_reach'] ?? 0) }}</p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-full">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- SEO Impressions -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">SEO Impressions</p>
                        <p class="text-2xl font-bold text-orange-600">
                            {{ number_format($dashboardData['seo_impressions'] ?? 0) }}</p>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-full">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Active Campaigns -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Active Campaigns</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ $activeCampaigns ?? 0 }}</p>
                    </div>
                    <div class="p-3 bg-indigo-50 rounded-full">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20.488 9H15V3.512A9.001 9.001 0 0120.488 9z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Hot Leads -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Hot Leads</p>
                        <p class="text-2xl font-bold text-red-600">{{ $hotLeads ?? 0 }}</p>
                    </div>
                    <div class="p-3 bg-red-50 rounded-full">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>



        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Today's AI Brief -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">📊 Today's AI Brief</h2>
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">Phase 2</span>
                    </div>
                    <div class="p-6">
                        @if(isset($todayBrief) && $todayBrief)
                            <div class="prose max-w-none">
                                <p class="text-gray-700">{{ $todayBrief->strategic_diagnosis }}</p>

                                @if($todayBrief->estimated_revenue_impact)
                                    <div class="mt-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                        <p class="text-sm text-green-700">
                                            💰 Estimated Revenue Impact:
                                            <strong>${{ number_format($todayBrief->estimated_revenue_impact, 2) }}</strong>
                                        </p>
                                    </div>
                                @endif

                                @if($todayBrief->confidence_score)
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500">
                                            Confidence Score: {{ $todayBrief->confidence_score }}%
                                        </p>
                                    </div>
                                @endif

                                <div class="mt-3">
                                    <a href="{{ route('briefs.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                                        View Full Brief →
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <p class="text-gray-500">No brief generated for today yet.</p>
                                <p class="text-sm text-gray-400 mt-1">Briefs are generated daily at 6:00 AM.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Action Queue -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">📋 Action Queue</h2>
                        <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">Phase 3</span>
                    </div>
                    <div class="p-6">
                        @livewire('action-queue')
                    </div>
                </div>

                <!-- Top Pages -->
<!-- Top Pages -->
@if(!empty($topPages))
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📈 Top Pages</h2>
        </div>
        <div class="p-6">
            <table class="min-w-full">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="pb-2 font-semibold">Page</th>
                        <th class="pb-2 text-right font-semibold">Visitors (30 days)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($topPages as $page)
                        <tr>
                            <td class="py-2 text-sm text-gray-700">
                                {{ $page['dimension'] ?? $page['path'] ?? '/' }}
                            </td>
                            <td class="py-2 text-sm text-gray-700 text-right">
                                {{ number_format($page['total_visitors'] ?? $page['visitors'] ?? 0) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-3">
                <a href="{{ route('analytics.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                    View Full Analytics →
                </a>
            </div>
        </div>
    </div>
@endif

                <!-- Recent Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">📋 Recent Actions</h2>
                        <a href="{{ route('actions.queue') }}"
                            class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full hover:bg-yellow-200 transition">
                            Pending: {{ $pendingActionsCount ?? 0 }}
                        </a>
                    </div>
                    <div class="p-6">
                        @if(!empty($recentActions))
                            <div class="space-y-3">
                                @foreach($recentActions as $action)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2">
                                                <span class="px-2 py-1 text-xs rounded-full 
                                                                {{ $action['category'] === 'seo' ? 'bg-blue-100 text-blue-800' : '' }}
                                                                {{ $action['category'] === 'content' ? 'bg-green-100 text-green-800' : '' }}
                                                                {{ $action['category'] === 'social' ? 'bg-purple-100 text-purple-800' : '' }}
                                                                {{ $action['category'] === 'strategy' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                                                {{ $action['category'] === 'campaign' ? 'bg-red-100 text-red-800' : '' }}
                                                            ">
                                                    {{ ucfirst($action['category']) }}
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    Priority: {{ $action['priority'] }}/5
                                                </span>
                                                <span class="text-xs px-2 py-1 rounded-full 
                                                                {{ $action['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                                {{ $action['status'] === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                                                {{ $action['status'] === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                                            ">
                                                    {{ ucfirst($action['status']) }}
                                                </span>
                                            </div>
                                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $action['title'] }}</p>
                                        </div>
                                        <a href="{{ route('actions.queue') }}" class="text-sm text-green-600 hover:text-green-700">
                                            View →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-3 text-center">
                                <a href="{{ route('actions.queue') }}" class="text-sm text-blue-600 hover:text-blue-800">
                                    View All Actions →
                                </a>
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No recent actions.</p>
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
                                        <p class="text-sm font-medium text-red-800">{{ $leak['opportunity_description'] }}</p>
                                        <p class="text-sm text-red-600 mt-1">
                                            Estimated Loss: ${{ number_format($leak['estimated_loss'], 2) }}
                                        </p>
                                        <p class="text-xs text-red-500 mt-1">
                                            Detected: {{ \Carbon\Carbon::parse($leak['detected_date'])->format('M d, Y') }}
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
                        <a href="{{ route('briefs.index') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            📝 Review AI Brief
                        </a>
                        <a href="{{ route('actions.queue') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            ✅ Approve Pending Actions
                        </a>
                        <a href="{{ route('leads.index') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            👤 Manage Leads
                        </a>
                        <a href="{{ route('campaigns.index') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            📊 View Campaigns
                        </a>
                        <a href="{{ route('content.drafts') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            ✍️ Review Content Drafts
                        </a>
                        <a href="{{ route('seo.index') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            🔍 SEO Assistant
                        </a>
                        <a href="{{ route('analytics.index') }}"
                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition">
                            📊 View Full Analytics
                        </a>
                    </div>
                </div>

                <!-- AI Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">🤖 AI Status</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-2">
                            <span
                                class="w-2 h-2 rounded-full {{ isset($aiStatus['available']) && $aiStatus['available'] ? 'bg-green-500' : 'bg-red-500' }}"></span>
                            <span class="text-sm text-gray-700">
                                {{ isset($aiStatus['available']) && $aiStatus['available'] ? 'Connected' : 'Offline' }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Provider: {{ $aiStatus['provider'] ?? 'Not configured' }}</p>
                        <p class="text-xs text-gray-500">Model: {{ $aiStatus['model'] ?? 'N/A' }}</p>
                        <p class="text-xs text-gray-500 mt-2">
                            Last Brief: {{ $aiStatus['last_brief'] ?? 'Never' }}
                        </p>
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">🛡️ Guardian Status</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="text-sm text-gray-700">All systems operational</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Total AI Calls: {{ $aiCallsCount ?? 0 }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Total Tokens Used: {{ number_format($totalTokensUsed ?? 0) }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Avg Response Time: {{ number_format($avgResponseTime ?? 0, 2) }}ms
                        </p>
                        <div class="mt-2">
                            <a href="{{ route('system.status') }}" class="text-xs text-blue-600 hover:text-blue-800">
                                View System Status →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Phase Progress -->
        <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">🚀 Platform Progress</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-600">✅</span>
                            <span class="font-medium text-green-800">Phase 0: Foundation</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Complete</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-600">✅</span>
                            <span class="font-medium text-green-800">Phase 1: Analytics</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Data collection active</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-600">✅</span>
                            <span class="font-medium text-green-800">Phase 2: AI Brief</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Daily briefs active</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-600">✅</span>
                            <span class="font-medium text-green-800">Phase 3: Human Gate</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Approval workflow active</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-600">✅</span>
                            <span class="font-medium text-green-800">Phase 4: Content</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Content generation active</p>
                    </div>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-600">✅</span>
                            <span class="font-medium text-green-800">Phase 5: Campaigns & Leads</span>
                        </div>
                        <p class="text-sm text-green-700 mt-1">Active</p>
                    </div>
                </div>
                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-sm text-blue-700">🔒 Phase 6: Guardian & Gateway - Active</p>
                    <p class="text-xs text-blue-600 mt-1">System health monitoring and domain routing active</p>
                </div>
            </div>
        </div>
    </div>
@endsection