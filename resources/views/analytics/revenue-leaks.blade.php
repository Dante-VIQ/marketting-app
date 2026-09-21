@extends('layouts.app')

@section('title', 'Revenue Leaks')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">💰 Revenue Leaks</h1>
            <p class="text-gray-600">Pages and campaigns losing money — detected by trend analysis</p>
        </div>
        <a href="{{ route('analytics.index') }}"
           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
            ← Back to Analytics
        </a>
    </div>

    @if(isset($leaks) && $leaks->count() > 0)
        <div class="space-y-4">
            @foreach($leaks as $leak)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 text-xs rounded-full
                                        {{ $leak->status === 'open' ? 'bg-rose-100 text-rose-800' : '' }}
                                        {{ $leak->status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    ">
                                        {{ ucfirst($leak->status) }}
                                    </span>
                                    @if($leak->source)
                                        <span class="text-xs text-gray-500">{{ ucfirst($leak->source) }}</span>
                                    @endif
                                    @if($leak->detected_date)
                                        <span class="text-xs text-gray-400">
                                            Detected {{ \Carbon\Carbon::parse($leak->detected_date)->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                                <h3 class="font-medium text-gray-900 truncate">
                                    {{ $leak->page_url ?? $leak->campaign_name ?? 'Unknown' }}
                                </h3>
                                @if($leak->opportunity_description)
                                    <p class="text-sm text-gray-600 mt-1">{{ $leak->opportunity_description }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500">Estimated loss</p>
                                <p class="text-2xl font-bold text-rose-600">
                                    ${{ number_format($leak->estimated_loss ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-12 text-center">
                <div class="text-6xl mb-4">🎉</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No revenue leaks detected</h3>
                <p class="text-gray-500">The system checks high-traffic pages for low conversion rates. Come back later.</p>
            </div>
        </div>
    @endif

</div>
@endsection