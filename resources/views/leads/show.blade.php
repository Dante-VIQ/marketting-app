@extends('layouts.app')

@section('title', 'Lead')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                {{ trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')) ?: 'Lead #' . $lead->id }}
            </h1>
            <p class="text-gray-600">{{ $lead->email }}</p>
        </div>
        <a href="{{ route('leads.index') }}"
           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm">
            ← Back to Leads
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">📋 Lead details</h2>
                </div>
                <div class="p-6 space-y-4">
                    @if($lead->message)
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Message</p>
                            <div class="bg-gray-50 p-3 rounded-lg text-sm text-gray-700 whitespace-pre-wrap">{{ $lead->message }}</div>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider">Category</p>
                            <p class="text-sm text-gray-900">{{ $lead->category_label ?? ucfirst($lead->category ?? 'unknown') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider">Source</p>
                            <p class="text-sm text-gray-900">{{ $lead->source_label ?? ucfirst($lead->source ?? 'unknown') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider">Score</p>
                            <p class="text-sm text-gray-900">{{ $lead->score_label ?? 'Unscored' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wider">Estimated value</p>
                            <p class="text-sm text-emerald-700 font-medium">
                                ${{ number_format($lead->estimated_value ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @if($lead->ai_summary || $lead->ai_suggested_response)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">🤖 AI insights</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        @if($lead->ai_summary)
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Summary</p>
                                <p class="text-sm text-gray-700">{{ $lead->ai_summary }}</p>
                            </div>
                        @endif
                        @if($lead->ai_suggested_response)
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Suggested response</p>
                                <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-lg text-sm text-emerald-900 whitespace-pre-wrap">
                                    {{ $lead->ai_suggested_response }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($lead->interactions && $lead->interactions->count() > 0)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">💬 Interaction history</h2>
                    </div>
                    <div class="p-6 space-y-3">
                        @foreach($lead->interactions as $interaction)
                            <div class="p-3 bg-gray-50 rounded-lg text-sm">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="text-xs font-medium text-gray-700">{{ ucfirst($interaction->type) }}</span>
                                    <span class="text-xs text-gray-400">{{ $interaction->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-gray-700 whitespace-pre-wrap">{{ $interaction->content }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Status</p>
                <p class="text-lg font-semibold text-gray-900">{{ $lead->status_label ?? ucfirst($lead->status) }}</p>
                @if($lead->last_contacted_at)
                    <p class="text-xs text-gray-500 mt-1">
                        Last contacted {{ $lead->last_contacted_at->diffForHumans() }}
                    </p>
                @endif
                @if($lead->follow_up_at)
                    <p class="text-xs text-amber-600 mt-1">
                        Follow-up due {{ $lead->follow_up_at->diffForHumans() }}
                    </p>
                @endif
            </div>

            @if($lead->phone || $lead->company || $lead->title)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-3 text-sm">
                    @if($lead->phone)
                        <div>
                            <p class="text-xs text-gray-500">Phone</p>
                            <p class="text-gray-900">{{ $lead->phone }}</p>
                        </div>
                    @endif
                    @if($lead->company)
                        <div>
                            <p class="text-xs text-gray-500">Company</p>
                            <p class="text-gray-900">{{ $lead->company }}</p>
                        </div>
                    @endif
                    @if($lead->title)
                        <div>
                            <p class="text-xs text-gray-500">Title</p>
                            <p class="text-gray-900">{{ $lead->title }}</p>
                        </div>
                    @endif
                </div>
            @endif

            @if($lead->notes)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Notes</p>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $lead->notes }}</p>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection