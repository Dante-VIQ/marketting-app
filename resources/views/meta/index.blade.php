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
                <p class="mt-1 text-sky-800">One token for everything built: <span class="font-mono">{{ implode(', ', $recommended) }}</span></p>
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


    {{-- Pull data --}}
    <div class="bg-white border rounded-lg p-4 text-sm">
        <h2 class="font-semibold text-gray-900">Pull data from Meta</h2>
        <p class="text-gray-600 mt-1">These also run on a schedule: ads daily, comments and leads every 30 minutes.</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach(['ads' => 'Ad performance', 'comments' => 'Customer comments', 'leads' => 'Lead form leads'] as $k => $label)
                <form method="POST" action="{{ route('meta.sync-data') }}">@csrf<input type="hidden" name="what" value="{{ $k }}">
                    <button class="px-3 py-1.5 text-xs border rounded-lg hover:bg-gray-50 disabled:opacity-40" @disabled(!$status['configured'])>{{ $label }}</button></form>
            @endforeach
        </div>
    </div>

    {{-- Who the ads reach --}}
    <div class="bg-white border rounded-lg p-4 text-sm">
        <h2 class="font-semibold text-gray-900">Who your ads reach (last 30 days)</h2>
        @if($ads['state'] === 'no_data')
            <p class="mt-1 text-gray-600">No ad data yet. Needs the ads_read permission and an ad account (set META_AD_ACCOUNT_ID if there are several). Press "Ad performance" above.</p>
        @elseif($ads['state'] === 'too_early')
            <p class="mt-1 text-gray-600">Only {{ number_format($ads['total']['impressions'] ?? 0) }} impressions so far. Breakdowns are shown once there are at least {{ \App\Services\Meta\AdAudienceInsights::MIN_TOTAL_IMPRESSIONS }}, because smaller samples mislead.</p>
        @else
            <p class="mt-1 text-gray-700">{{ number_format($ads['total']['impressions']) }} impressions · {{ $ads['total']['clicks'] === null ? '—' : number_format($ads['total']['clicks']) }} clicks · spend {{ $ads['total']['spend'] === null ? '—' : number_format($ads['total']['spend'], 2) . ' ' . $ads['currency'] }}@if($ads['total']['results'] !== null) · {{ $ads['total']['results'] }} leads @endif</p>
            @foreach(['age_gender' => 'Age and gender', 'country' => 'Country', 'placement' => 'Where ads showed'] as $k => $title)
                @if(isset($ads['groups'][$k]))
                    <h3 class="mt-3 font-medium text-gray-900">{{ $title }}@if($ads['groups'][$k]['best_ctr']) <span class="text-xs font-normal text-emerald-700">· best click rate: {{ $ads['groups'][$k]['best_ctr'] }}</span>@endif</h3>
                    <table class="w-full text-xs mt-1">
                        <thead><tr class="text-left text-gray-500"><th class="py-1">Group</th><th>Impressions</th><th>Clicks</th><th>Click rate</th><th>Cost per click</th><th>Spend share</th></tr></thead>
                        <tbody>
                        @foreach(array_slice($ads['groups'][$k]['rows'], 0, 8) as $r)
                            <tr class="border-t"><td class="py-1">{{ $r['name'] }}</td><td>{{ $r['impressions'] === null ? '—' : number_format($r['impressions']) }}</td><td>{{ $r['clicks'] ?? '—' }}</td>
                                <td>{{ $r['ctr'] === null ? '—' : $r['ctr'] . '%' }}</td><td>{{ $r['cpc'] ?? '—' }}</td><td>{{ $r['spend_share'] === null ? '—' : $r['spend_share'] . '%' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif
    </div>

    {{-- Customer voice --}}
    <div class="bg-white border rounded-lg p-4 text-sm">
        <h2 class="font-semibold text-gray-900">What people say under your posts</h2>
        @if($commentCount === 0)
            <p class="mt-1 text-gray-600">No comments saved yet. Needs pages_read_user_content (Facebook) and instagram_manage_comments (Instagram).</p>
        @else
            <p class="mt-1 text-gray-700">{{ $commentCount }} comments saved
                @foreach($sentiment as $k => $n)· {{ $n }} {{ $k }} @endforeach
                <span class="text-xs text-gray-500">(sentiment is a rough word-based guess)</span></p>
            @if($questions->isNotEmpty())
                <h3 class="mt-3 font-medium text-gray-900">Recent questions and buying signals</h3>
                @foreach($questions as $c)<p class="text-xs text-gray-700 border-t py-1">{{ $c->platform }} · {{ \Illuminate\Support\Str::limit($c->body, 200) }}</p>@endforeach
            @endif
        @endif
    </div>

    {{-- Audience research --}}
    <div class="bg-white border rounded-lg p-4 text-sm">
        <h2 class="font-semibold text-gray-900">Audience research</h2>
        <p class="text-gray-600 mt-1">How many people Meta says are interested in a topic. Sizes are Meta's estimates, shown as ranges, worldwide.</p>
        <form method="GET" action="{{ route('meta.index') }}" class="mt-2 flex gap-2">
            <input name="q" value="{{ $q }}" placeholder="e.g. safari, budget travel, Maasai Mara" class="flex-1 border rounded-lg px-3 py-1.5 text-sm">
            <button class="px-3 py-1.5 text-xs border rounded-lg hover:bg-gray-50" @disabled(!$status['configured'])>Search</button>
        </form>
        @if($researchError)<p class="mt-2 text-amber-800 text-xs">{{ $researchError }}</p>@endif
        @if($q !== '' && !$researchError && !$interests)<p class="mt-2 text-gray-600 text-xs">Nothing found for "{{ $q }}".</p>@endif
        @foreach($interests as $i)
            <p class="text-xs border-t py-1 text-gray-800">{{ $i['name'] }}
                <span class="text-gray-500">· {{ $i['low'] === null ? 'size unknown' : number_format($i['low']) . ' – ' . number_format($i['high']) }}@if($i['topic']) · {{ $i['topic'] }}@endif</span></p>
        @endforeach
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
