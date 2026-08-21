@extends('layouts.app')

@section('title', $post->title)

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $post->title }}</h1>
                <p class="text-gray-600">
                    {{ $post->published_at ? \Carbon\Carbon::parse($post->published_at)->format('F j, Y') : 'Draft' }}
                </p>
            </div>
            <a href="{{ route('blog.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                ← Back to Blog
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <div class="prose max-w-none">
                {!! $post->content !!}
            </div>

            <!-- Post Metadata -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $post->status === 'published' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $post->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $post->status === 'archived' ? 'bg-gray-100 text-gray-800' : '' }}
                        ">
                            {{ ucfirst($post->status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Views</p>
                        <p class="font-medium">{{ number_format($post->views ?? 0) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Source</p>
                        <p class="font-medium">{{ ucfirst($post->source ?? 'manual') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Word Count</p>
                        <p class="font-medium">{{ $post->word_count }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection