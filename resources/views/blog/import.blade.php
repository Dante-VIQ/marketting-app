@extends('layouts.app')

@section('title', 'Import Blog Posts')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">📥 Import Blog Posts</h1>
                <p class="text-gray-600">Import your existing blog posts</p>
            </div>
            <a href="{{ route('blog.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                ← Back to Blog
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- WordPress Import -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">WordPress</h3>
                    <p class="text-sm text-gray-600 mt-1">Import from WordPress REST API</p>
                    <form action="{{ route('blog.import.wordpress') }}" method="POST" class="mt-4">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700">WordPress URL</label>
                            <input type="url" name="url" placeholder="https://your-site.com" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Import from WordPress
                        </button>
                    </form>
                </div>

                <!-- CSV Import -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">CSV</h3>
                    <p class="text-sm text-gray-600 mt-1">Upload a CSV file</p>
                    <form action="{{ route('blog.import.csv') }}" method="POST" enctype="multipart/form-data" class="mt-4">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700">CSV File</label>
                            <input type="file" name="csv_file" accept=".csv" 
                                   class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Upload CSV
                        </button>
                    </form>
                </div>

                <!-- Manual Import -->
                <div class="p-4 border border-gray-200 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900">Manual</h3>
                    <p class="text-sm text-gray-600 mt-1">Add a single post manually</p>
                    <form action="{{ route('blog.import.manual') }}" method="POST" class="mt-4">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" placeholder="Post title" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700">Content</label>
                            <textarea name="content" rows="3" placeholder="Post content..." 
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                        </div>
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Add Post
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection