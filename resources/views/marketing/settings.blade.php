@extends('layouts.app')

@section('title', 'Marketing Settings')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">⚙️ Marketing Settings</h1>
        <p class="text-gray-600">Configure AI provider, brand voice, and automation rules</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-12 text-center">
            <div class="text-6xl mb-4">⚙️</div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Settings in progress</h3>
            <p class="text-gray-500">
                Brand voice and provider configuration are managed per-brand in <a href="{{ route('brands.index') }}" class="text-blue-600 hover:text-blue-800">Brand Settings</a>.
            </p>
        </div>
    </div>
</div>
@endsection