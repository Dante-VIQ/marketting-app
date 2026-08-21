@extends('layouts.app')

@section('title', 'Scan Page')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
<div class="max-w-2xl mx-auto">
<div class="mb-6">
<div class="flex items-center justify-between">
<div>
<h1 class="text-2xl font-bold text-gray-900">🔍 Scan Page</h1>
<p class="text-gray-600">Enter a URL to scan and analyze</p>
</div>
<a href="{{ route('scanner.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
← Back
</a>
</div>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
<div class="p-6">
<form action="{{ route('scanner.scan') }}" method="POST">
@csrf
<div class="mb-4">
<label class="block text-sm font-medium text-gray-700 mb-1">URL to Scan</label>
<input type="url" name="url" required
placeholder="https://example.com/page"
class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
</div>
<div class="mb-4">
<label class="block text-sm font-medium text-gray-700 mb-1">Related Action (Optional)</label>
<select name="action_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
<option value="">No related action</option>
@foreach($actions as $action)
<option value="{{ $action->id }}">
{{ $action->title }} ({{ $action->category }})
</option>
@endforeach
</select>
</div>
<button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
🔍 Scan Page
</button>
</form>
</div>
</div>
</div>
</div>
@endsection
