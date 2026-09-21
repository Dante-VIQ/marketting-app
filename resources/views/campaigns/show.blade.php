@extends('layouts.app')

@section('title', $campaign->name ?? 'Campaign')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-800">
                    {{ ucfirst($campaign->type ?? 'other') }}
                </span>
                <span class="px-2 py-0.5 text-xs rounded-full
                    {{ $campaign->status === 'active' ? 'bg-emerald-100 text-emerald-800' : '' }}
                    {{ $campaign->status === 'paused' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $campaign->status === 'completed' ? 'bg-gray-100 text-gray-700' : '' }}
                    {{ $campaign->status === 'draft' ? 'bg-blue-100 text-blue-800' : '' }}
                ">
                    {{ ucfirst($campaign->status ?? 'draft') }}
                </span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $campaign->name }}</h1>
            @if($campaign->description)
                <p class="text-gray-600 mt-1">{{ $campaign->description }}</p>
            @endif
        </div>
        <a href="{{ route('campaigns.index') }}"
           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
            ← Back to Campaigns
        </a>
    </div>

    {{-- Metrics --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Budget</p>
            <p class="text-2xl font-bold text-gray-900">${{ number_format($campaign->budget ?? 0, 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Spent</p>
            <p class="text-2xl font-bold text-rose-600">${{ number_format($campaign->spent ?? 0, 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Revenue</p>
            <p class="text-2xl font-bold text-emerald-600">${{ number_format($campaign->revenue ?? 0, 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">ROI</p>
            @php $roi = $campaign->roi ?? []; @endphp
            <p class="text-2xl font-bold {{ ($roi['roi_percentage'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ number_format($roi['roi_percentage'] ?? 0, 1) }}%
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Impressions</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->impressions ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Clicks</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->clicks ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Leads</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->leads ?? 0) }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Conversions</p>
            <p class="text-xl font-bold text-gray-900">{{ number_format($campaign->conversions ?? 0) }}</p>
        </div>
    </div>

    {{-- Dates --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Start date</p>
                <p class="text-gray-900 font-medium">
                    {{ $campaign->start_date ? \Carbon\Carbon::parse($campaign->start_date)->format('F j, Y') : '—' }}
                </p>
            </div>
            <div>
                <p class="text-gray-500">End date</p>
                <p class="text-gray-900 font-medium">
                    {{ $campaign->end_date ? \Carbon\Carbon::parse($campaign->end_date)->format('F j, Y') : '—' }}
                </p>
            </div>
        </div>
    </div>

</div>
@endsection