@extends('layouts.app')

@section('title', 'Coming Soon')

@section('content')
<div class="py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto text-center">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-12">
            <div class="text-6xl mb-6">🚀</div>
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $feature }}</h1>
            <p class="text-gray-600 text-lg mb-6">
                This feature is coming soon! We're working hard to bring you the best AI experience.
            </p>
            <div class="flex items-center justify-center space-x-4">
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                    <span class="text-sm text-gray-600">Phase 1: Marketing AI</span>
                </div>
                <span class="text-gray-300">→</span>
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></span>
                    <span class="text-sm text-gray-600 font-medium">{{ $feature }}</span>
                </div>
                <span class="text-gray-300">→</span>
                <div class="flex items-center space-x-2 opacity-50">
                    <span class="w-3 h-3 bg-gray-300 rounded-full"></span>
                    <span class="text-sm text-gray-400">More to come</span>
                </div>
            </div>
            <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <p class="text-sm text-blue-700">
                    💡 Want to be notified when {{ $feature }} launches? 
                    <a href="#" class="text-blue-600 font-medium hover:underline">Join the waitlist</a>
                </p>
            </div>
            <div class="mt-8">
                <a href="{{ route('dashboard') }}" class="text-green-600 hover:text-green-700 font-medium">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
@endsection