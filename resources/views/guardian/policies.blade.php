@extends('layouts.app')

@section('title', 'Guardian Policies')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">📋 Guardian Policies</h1>
        <p class="text-gray-600">Security and compliance policies</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <p class="text-gray-500 text-center py-8">No policies configured.</p>
        </div>
    </div>
</div>
@endsection