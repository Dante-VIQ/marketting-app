@extends('layouts.app')

@section('title', 'Marketing AI')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">📈 Marketing AI</h1>
        <p class="text-gray-600">Your AI Chief Marketing Officer</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Today's Brief</h2>
            </div>
            <div class="p-6">
                <p class="text-gray-500 text-center py-4">No brief generated for today yet.</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">AI Status</h2>
            </div>
            <div class="p-6">
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Provider</span>
                        <span class="text-sm font-medium text-gray-700">{{ config('ai.provider', 'ollama') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Model</span>
                        <span class="text-sm font-medium text-gray-700">{{ config('ai.providers.' . config('ai.provider') . '.model', 'N/A') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Status</span>
                        <span class="text-sm font-medium text-green-600">Connected</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection