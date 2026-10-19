@extends('layouts.app')

@section('title', 'Meta Connection')

@php
    $t = $status['token'];
    $groups = collect($status['capabilities'])->groupBy('group');
    $soon = $t && $t['expires_at'] && \Illuminate\Support\Carbon::parse($t['expires_at'])->lt(now()->addDays(7));
@endphp

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6 max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">⚡ Meta Connection</h1>
            <p class="text-gray-600">Everything the free Meta APIs can give {{ $brand->name }}, and what your token unlocks today.</p>
        </div>
        <form method="POST" action="{{ route('meta.sync') }}">@csrf
            <button class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-40" @disabled(!$status['configured'])>Import posts and refresh results now</button>
        </form>
    </div>

    @if(session('message'))<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    {{-- Token --}}
    <div class="bg-white border rounded-lg p-4 text-sm">
        <h2 class="font-semibold text-gray-900">Token</h2>
        @if(!$status['configured'])
            <p class="mt-1 text-red-700">{{ $status['error'] }}</p>
        @elseif(!$t)
            <p class="mt-1 text-red-700">Could not inspect the token: {{ $status['error'] }}</p>
        @else
            <p class="mt-1 {{ $t['valid'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $t['valid'] ? 'Valid' : 'Not valid' }}
                · type {{ $t['type'] ?? 'unknown' }}
                · {{ $t['never_expires'] ? 'does not expire' : ($t['expires_at'] ? 'expires ' . $t['expires_at'] : 'expiry unknown') }}
                · {{ count($t['scopes']) }} permissions</p>
            @if($soon)
                <p class="mt-2 p-2 rounded bg-amber-50 border border-amber-200 text-amber-900 text-xs">This token expires within a week. Tokens from the Graph API Explorer are short-lived. For a permanent setup, create a System User in Business Settings and generate its token with the permissions below, or exchange this one for a long-lived token.</p>
            @endif
            @if($t['data_access_expires_at'])<p class="text-xs text-gray-500 mt-1">Data access renews by {{ $t['data_access_expires_at'] }}.</p>@endif
        @endif

        @if($status['missing'])
            <div class="mt-3 p-3 rounded bg-sky-50 border border-sky-200 text-xs text-sky-900">
                <p class="font-semibold">Add these permissions to unlock features the app already has:</p>
                <p class="mt-1 font-mono">{{ implode(', ', $status['missing']) }}</p>
                <p class="mt-1">In the Meta app, add the Page and Instagram use cases (under "Content management"), then generate the token again with these ticked. Pages and Instagram permissions are free; other people's accounts need Meta App Review, your own do not.</p>
            </div>
        @endif
    </div>

    {{-- Pages --}}
    @if($status['pages'] || $status['pages_error'])
        <div class="bg-white border rounded-lg p-4 text-sm">
            <h2 class="font-semibold text-gray-900">Pages this token can use</h2>
            @if($status['pages_error'])<p class="text-red-700 mt-1">{{ $status['pages_error'] }}</p>@endif
            @foreach($status['pages'] as $p)
                <p class="mt-1 text-gray-800">{{ $p['name'] }} <span class="text-gray-400">#{{ $p['id'] }}</span>
                    @if($p['instagram'])· Instagram &#64;{{ $p['instagram'] }}@else<span class="text-amber-700">· no Instagram linked</span>@endif
                    @unless($p['can_post'])<span class="text-amber-700">· your role cannot post</span>@endunless</p>
            @endforeach
            @if(count($status['pages']) > 1)<p class="text-xs text-gray-500 mt-2">Several Pages: set META_PAGE_ID in .env so the right one is used.</p>@endif
        </div>
    @endif

    {{-- Publishing --}}
    <div class="bg-white border rounded-lg p-4 text-sm">
        <h2 class="font-semibold text-gray-900">Posting</h2>
        <p class="mt-1 text-gray-700">You can always post by hand and record it in the Content Plan. Auto-posting is
            <strong class="{{ $publishing ? 'text-emerald-700' : 'text-gray-700' }}">{{ $publishing ? 'ON' : 'OFF' }}</strong>
            (META_PUBLISHING_ENABLED). When on, the system only posts text you approved, at the time you chose, up to {{ $cap }} posts a day per brand, and never retries a failed post on its own.</p>
    </div>

    {{-- Capability matrix --}}
    @foreach($groups as $group => $caps)
        <div class="bg-white border rounded-lg p-4 text-sm">
            <h2 class="font-semibold text-gray-900">{{ $group }}</h2>
            @foreach($caps as $c)
                <div class="py-2 border-t flex flex-wrap items-start justify-between gap-2">
                    <div class="max-w-xl">
                        <p class="text-gray-900">{{ $c['label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $c['used_for'] }}{{ $c['built'] ? '' : ' (not built yet)' }}</p>
                    </div>
                    <div class="text-right text-xs">
                        @if($c['available'])
                            <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Available</span>
                        @else
                            <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700">Needs {{ implode(', ', $c['missing']) }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
