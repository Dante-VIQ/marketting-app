@extends('layouts.app')

@section('title', 'System Status')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">🛡️ Guardian Status</h1>
        <p class="text-gray-600">System health monitoring and security overview</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- System Health -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">System Health</h2>
                </div>
                <div class="p-6">
                    @if(empty($healthStatus))
                        <p class="text-gray-500 text-center py-4">No health data available.</p>
                    @else
                        <div class="space-y-4">
                            @foreach($healthStatus as $component => $status)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <span class="w-3 h-3 rounded-full 
                                            {{ $status['status'] === 'healthy' ? 'bg-green-500' : '' }}
                                            {{ $status['status'] === 'degraded' ? 'bg-yellow-500' : '' }}
                                            {{ $status['status'] === 'down' ? 'bg-red-500' : '' }}
                                        "></span>
                                        <span class="font-medium text-gray-900">{{ ucfirst($component) }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm text-gray-500">{{ $status['status_label'] }}</span>
                                        @if($status['response_time_ms'])
                                            <span class="text-xs text-gray-400 ml-2">{{ $status['response_time_ms'] }}ms</span>
                                        @endif
                                        <p class="text-xs text-gray-400">{{ $status['checked_at'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Open Incidents -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Open Incidents</h2>
                </div>
                <div class="p-6">
                    @if(empty($incidents))
                        <div class="text-center py-6">
                            <p class="text-green-600 text-2xl">✅</p>
                            <p class="text-gray-500 mt-2">All systems operational</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($incidents as $incident)
                                <div class="p-3 rounded-lg border 
                                    {{ $incident['severity'] === 'critical' ? 'bg-red-50 border-red-200' : '' }}
                                    {{ $incident['severity'] === 'high' ? 'bg-orange-50 border-orange-200' : '' }}
                                    {{ $incident['severity'] === 'medium' ? 'bg-yellow-50 border-yellow-200' : '' }}
                                    {{ $incident['severity'] === 'low' ? 'bg-blue-50 border-blue-200' : '' }}
                                ">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-900">{{ $incident['type'] }}</span>
                                        <span class="text-xs px-2 py-1 rounded-full 
                                            {{ $incident['severity'] === 'critical' ? 'bg-red-600 text-white' : '' }}
                                            {{ $incident['severity'] === 'high' ? 'bg-orange-600 text-white' : '' }}
                                            {{ $incident['severity'] === 'medium' ? 'bg-yellow-600 text-white' : '' }}
                                            {{ $incident['severity'] === 'low' ? 'bg-blue-600 text-white' : '' }}
                                        ">
                                            {{ ucfirst($incident['severity']) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-1">{{ $incident['description'] }}</p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ \Carbon\Carbon::parse($incident['created_at'])->diffForHumans() }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection