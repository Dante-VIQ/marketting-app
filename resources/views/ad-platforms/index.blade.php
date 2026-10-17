@extends('layouts.app')

@section('title', 'Ad Platforms')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">📣 Ad Platforms</h1>
        <p class="text-gray-600">Let the agent pause campaigns and adjust budgets on the real platform. Every action still waits for your approval and can be rolled back.</p>
    </div>

    @if(session('message'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>
    @endif
    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Platforms --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($platforms as $p)
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">{{ $p['label'] }}</h2>
                    @if($p['configured'])
                        <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Credentials found</span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-800">Not configured</span>
                    @endif
                </div>
                @unless($p['configured'])
                    <p class="text-sm text-gray-600 mt-2">Add to your <code>.env</code> and reload config:
                        @foreach($p['env'] as $var)<code class="px-1 bg-gray-100 rounded">{{ $var }}</code>@endforeach
                    </p>
                @endunless
                <p class="text-xs text-gray-400 mt-2">Budget changes are limited to {{ round($maxChange * 100) }}% per action.</p>
            </div>
        @endforeach
    </div>

    {{-- Campaigns --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Campaigns</h2>
            <p class="text-xs text-gray-500">The agent only acts on campaigns linked to a platform campaign id.</p>
        </div>
        <div class="p-6">
            @forelse($campaigns as $c)
                <div class="py-3 border-b border-gray-100 last:border-0">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <a href="{{ route('campaigns.show', $c->id) }}" class="font-medium text-gray-900 hover:text-blue-600">{{ $c->name }}</a>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ ucfirst($c->status) }}</span>
                            @if($c->platform)
                                <span class="ml-1 text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">{{ ucfirst($c->platform) }} · {{ $c->external_id }}</span>
                            @else
                                <span class="ml-1 text-xs text-gray-400">Not linked</span>
                            @endif
                        </div>

                        @if($c->platform)
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('ad-platforms.check', $c->id) }}">
                                    @csrf
                                    <button class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Check connection</button>
                                </form>
                                <form method="POST" action="{{ route('ad-platforms.unlink', $c->id) }}"
                                      onsubmit="return confirm('Unlink this campaign? The agent will stop acting on it.')">
                                    @csrf
                                    <button class="px-3 py-1.5 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Unlink</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('ad-platforms.link', $c->id) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="platform" class="text-sm border-gray-300 rounded-lg">
                                    @foreach($platforms as $p)<option value="{{ $p['name'] }}">{{ $p['label'] }}</option>@endforeach
                                </select>
                                <input name="external_id" inputmode="numeric" pattern="[0-9]+" required
                                       placeholder="{{ $platforms[0]['id_hint'] ?? 'Campaign id' }}"
                                       class="text-sm border-gray-300 rounded-lg w-56">
                                <button class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Link</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-center py-4">No campaigns yet. Create a campaign first, then link it here.</p>
            @endforelse
        </div>
    </div>

    {{-- Agent ad actions --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Recent ad actions</h2>
            <a href="{{ route('actions.queue') }}" class="text-sm text-blue-600 hover:text-blue-800">Approve in Action Queue →</a>
        </div>
        <div class="p-6">
            @forelse($actions as $a)
                <div class="py-2 border-b border-gray-100 last:border-0 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $a->title }}</p>
                        <p class="text-xs text-gray-500">{{ $a->created_at->diffForHumans() }} · {{ str_replace('_', ' ', $a->ad_operation) }}</p>
                    </div>
                    @if($a->reversed_at)
                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">Rolled back</span>
                    @elseif($a->executed_at)
                        <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Applied</span>
                    @else
                        <span class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-800">{{ ucfirst($a->status) }}</span>
                    @endif
                </div>
            @empty
                <p class="text-gray-500 text-center py-4">No ad actions yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
