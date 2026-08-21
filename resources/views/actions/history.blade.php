@extends('layouts.app')

@section('title', 'Action History')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">📋 Action History</h1>
        <p class="text-gray-600">View all approved and rejected actions</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <p class="text-gray-500 text-center py-8">No actions found.</p>
        </div>
    </div>
</div>
@endsection