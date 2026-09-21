@extends('layouts.app')

@section('title', 'Travel Guides')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🗺️ Travel Guides</h1>
            <p class="text-gray-600">Long-form destination guides with embedded affiliate offers</p>
        </div>
        <a href="{{ route('guides.suggest') }}"
           class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition text-sm">
            ✨ Suggest New Guides
        </a>
    </div>

    @if(session('message'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-800">
            {{ session('message') }}
        </div>
    @endif

    @if(isset($guides) && $guides->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($guides as $guide)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition">
                    @if($guide->featured_image)
                        <img src="{{ $guide->featured_image }}" alt="{{ $guide->title }}" class="w-full h-40 object-cover">
                    @endif
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 text-xs rounded-full
                                {{ $guide->status === 'published' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $guide->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            ">
                                {{ ucfirst($guide->status) }}
                            </span>
                            @if($guide->destination)
                                <span class="text-xs text-gray-500">{{ $guide->destination }}</span>
                            @endif
                        </div>
                        <h3 class="font-medium text-gray-900 line-clamp-2">{{ $guide->title }}</h3>
                        @if($guide->description)
                            <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ Str::limit($guide->description, 100) }}</p>
                        @endif
                        <div class="flex items-center gap-4 mt-3 text-xs text-gray-500">
                            <span>👁 {{ number_format($guide->views ?? 0) }}</span>
                            <span>🎫 {{ number_format($guide->bookings_generated ?? 0) }} bookings</span>
                            @if($guide->revenue_generated > 0)
                                <span class="text-emerald-600 font-medium">${{ number_format($guide->revenue_generated, 0) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-12 text-center">
                <div class="text-6xl mb-4">🗺️</div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No travel guides yet</h3>
                <p class="text-gray-500">Click <strong>Suggest New Guides</strong> to have the AI propose destinations
                    based on your top blog posts and affiliate inventory.</p>
            </div>
        </div>
    @endif

</div>
@endsection