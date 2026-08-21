@extends('layouts.app')

@section('title', 'Blog')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📝 Blog</h1>
                <p class="text-gray-600">Manage your blog posts and content</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form action="{{ route('blog.generate-all') }}" method="POST" 
                      onsubmit="return confirm('Generate content for all approved actions? This may take a few moments.');">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        ⚡ Generate All Content
                    </button>
                </form>
                <a href="{{ route('blog.import') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    📥 Import Posts
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Total Posts</p>
            <p class="text-2xl font-bold text-gray-900">{{ $totalPosts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Published</p>
            <p class="text-2xl font-bold text-green-600">{{ $publishedPosts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Drafts</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $draftPosts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">AI Generated</p>
            <p class="text-2xl font-bold text-blue-600">{{ $aiGeneratedPosts ?? 0 }}</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
            <p class="text-sm text-gray-500">Pending Content</p>
            <p class="text-2xl font-bold text-purple-600">{{ $pendingContent ?? 0 }}</p>
        </div>
    </div>

    @if(($pendingContent ?? 0) > 0)
        <div class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <p class="text-sm text-blue-700">
                💡 <strong>{{ $pendingContent }}</strong> approved action(s) are waiting for content generation.
                Click the "Generate All Content" button above to create drafts.
            </p>
        </div>
    @endif

    <!-- Posts List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">📄 Blog Posts</h2>
        </div>
        <div class="p-6">
            @if($posts->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="pb-2 font-semibold">Title</th>
                                <th class="pb-2 font-semibold">Status</th>
                                <th class="pb-2 text-right font-semibold">Views</th>
                                <th class="pb-2 text-right font-semibold">Published</th>
                                <th class="pb-2 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($posts as $post)
                                <tr>
                                    <td class="py-2 text-sm font-medium text-gray-900">
                                        <a href="{{ route('blog.show', $post->id) }}" class="hover:text-blue-600">
                                            {{ $post->title }}
                                        </a>
                                    </td>
                                    <td class="py-2">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $post->status === 'published' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $post->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $post->status === 'archived' ? 'bg-gray-100 text-gray-800' : '' }}
                                        ">
                                            {{ ucfirst($post->status) }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-sm text-gray-700 text-right">
                                        {{ number_format($post->views ?? 0) }}
                                    </td>
                                    <td class="py-2 text-sm text-gray-700 text-right">
                                        {{ $post->published_at ? \Carbon\Carbon::parse($post->published_at)->format('M d, Y') : 'Never' }}
                                    </td>
                                    <td class="py-2 text-sm text-gray-700 text-right">
                                        <a href="{{ route('blog.show', $post->id) }}" class="text-blue-600 hover:text-blue-800">
                                            View →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 text-center py-4">No blog posts found.</p>
            @endif
        </div>
    </div>
</div>
@endsection