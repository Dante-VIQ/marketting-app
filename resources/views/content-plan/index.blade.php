@extends('layouts.app')

@section('title', 'Content Plan')

@php
    $platforms = \App\Models\ContentIdea::PLATFORMS;
    $formats = \App\Models\ContentIdea::FORMATS;
    $srcLabel = ['manual' => 'Your idea', 'competitor_gap' => 'Competitor gap', 'listening' => 'Social listening', 'keyword' => 'SEO keyword', 'persona' => 'Persona pain', 'audience_comment' => 'Audience question'];
    $todo = collect($ideas->get('ready', []))->concat($ideas->get('idea', []));
    $todo = $todo->concat($ideas->get('failed', []));
    $scheduled = collect($ideas->get('scheduled', []))->concat($ideas->get('publishing', []));
    $autoOn = \App\Services\Meta\ContentPublisher::enabled();
    $posted = collect($ideas->get('posted', []));
    $pct = fn ($n) => $n === null ? '—' : $n . '%';
@endphp

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">🗓️ Content Plan</h1>
        <p class="text-gray-600">What to post for {{ $brand->name }}, and what is working. You post by hand; the system suggests, and learns from the results you record.</p>
    </div>

    @if(session('message'))<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    {{-- What is working --}}
    <div class="p-4 bg-white border rounded-lg text-sm">
        <h2 class="font-semibold text-gray-900">What is working</h2>
        @if($performance['state'] === 'no_results')
            <p class="text-gray-600 mt-1">No results recorded yet. After you post, add reach and engagements for each post below. Patterns appear after {{ \App\Services\ContentPlan\ContentPerformance::MIN_TOTAL }} measured posts.</p>
        @elseif($performance['state'] === 'too_early')
            <p class="text-amber-800 mt-1">Too early to tell: {{ $performance['measured_posts'] }} measured {{ $performance['measured_posts'] === 1 ? 'post' : 'posts' }}, {{ $performance['needed'] }} more needed before we name a winner. Overall engagement so far: {{ $pct($performance['overall_engagement_rate']) }}.</p>
        @else
            <p class="text-gray-600 mt-1">{{ $performance['measured_posts'] }} measured posts · overall engagement rate {{ $pct($performance['overall_engagement_rate']) }}. A group only leads once it has at least {{ \App\Services\ContentPlan\ContentPerformance::MIN_GROUP }} posts.</p>
            <div class="mt-2 grid md:grid-cols-4 gap-4">
                @foreach(['platform' => 'Platform', 'format' => 'Format', 'topic' => 'Topic', 'weekday' => 'Day'] as $dim => $label)
                    <div><p class="text-xs uppercase text-gray-500">{{ $label }}</p>
                        @forelse($performance['groups'][$dim] ?? [] as $g)
                            <p class="{{ ($performance['leaders'][$dim]['label'] ?? null) === $g['label'] ? 'font-semibold text-emerald-700' : 'text-gray-700' }}">
                                {{ $platforms[$g['label']] ?? $formats[$g['label']] ?? $g['label'] }}: {{ $g['engagement_rate'] }}% <span class="text-gray-400">({{ $g['posts'] }})</span></p>
                        @empty<p class="text-gray-400">—</p>@endforelse
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Suggestions --}}
    <div class="bg-white border rounded-lg p-4">
        <h2 class="font-semibold text-gray-900">Suggested by your data</h2>
        @forelse($suggestions as $s)
            <form method="POST" action="{{ route('content-plan.store') }}" class="py-2 border-t flex flex-wrap items-center justify-between gap-2 text-sm">
                @csrf
                <input type="hidden" name="title" value="{{ $s['title'] }}"><input type="hidden" name="topic" value="{{ $s['topic'] }}">
                <input type="hidden" name="source" value="{{ $s['source'] }}"><input type="hidden" name="source_detail" value="{{ $s['source_detail'] }}">
                <div><p class="text-gray-900">{{ $s['title'] }} <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-sky-100 text-sky-800">{{ $srcLabel[$s['source']] }}</span></p>
                    <p class="text-xs text-gray-500">{{ $s['source_detail'] }}</p></div>
                <button class="px-3 py-1 bg-blue-600 text-white rounded">Add to plan</button>
            </form>
        @empty
            <p class="text-sm text-gray-600 mt-1">Nothing to suggest yet. Suggestions come from competitor topic gaps, social-listening conversations, keywords close to page one and your personas' pains. Run those pages' checks, or add your own idea below.</p>
        @endforelse
    </div>

    {{-- Scheduled auto-posts --}}
    @if($scheduled->isNotEmpty())
        <div class="space-y-3">
            <h2 class="font-semibold text-gray-900">Approved, will post automatically ({{ $scheduled->count() }})</h2>
            @foreach($scheduled as $i)
                <div class="bg-white border border-sky-200 rounded-lg p-4 text-sm flex flex-wrap justify-between gap-2">
                    <div><p class="font-medium text-gray-900">{{ $i->title }} <span class="text-gray-400 font-normal">· {{ $platforms[$i->platform] ?? '' }}</span></p>
                        <p class="text-xs text-gray-500">{{ $i->status === 'publishing' ? 'Posting now…' : 'Goes out ' . $i->scheduled_at?->format('D j M, H:i') }}</p></div>
                    @if($i->status === 'scheduled')
                        <form method="POST" action="{{ route('content-plan.cancel-schedule', $i->id) }}">@csrf<button class="text-xs text-red-600">Cancel auto-post</button></form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- To post --}}
    <div class="space-y-3">
        <h2 class="font-semibold text-gray-900">To post ({{ $todo->count() }})</h2>
        @foreach($todo as $i)
            <div class="bg-white border rounded-lg p-4 text-sm space-y-2">
                <div class="flex flex-wrap justify-between gap-2">
                    <p class="font-medium text-gray-900">{{ $i->title }}
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{{ $srcLabel[$i->source] ?? $i->source }}</span></p>
                    <form method="POST" action="{{ route('content-plan.destroy', $i->id) }}">@csrf @method('DELETE')<button class="text-xs text-red-600">remove</button></form>
                </div>
                @if($i->source_detail)<p class="text-xs text-gray-500">{{ $i->source_detail }}</p>@endif
                <form method="POST" action="{{ route('content-plan.update', $i->id) }}" class="grid md:grid-cols-4 gap-2">
                    @csrf @method('PUT')
                    <input type="hidden" name="title" value="{{ $i->title }}"><input type="hidden" name="topic" value="{{ $i->topic }}">
                    <select name="platform" class="border rounded px-2 py-1"><option value="">Platform</option>@foreach($platforms as $k => $l)<option value="{{ $k }}" @selected($i->platform === $k)>{{ $l }}</option>@endforeach</select>
                    <select name="format" class="border rounded px-2 py-1"><option value="">Format</option>@foreach($formats as $k => $l)<option value="{{ $k }}" @selected($i->format === $k)>{{ $l }}</option>@endforeach</select>
                    <input type="date" name="planned_for" value="{{ $i->planned_for?->format('Y-m-d') }}" class="border rounded px-2 py-1">
                    <select name="status" class="border rounded px-2 py-1"><option value="idea" @selected($i->status === 'idea')>Idea</option><option value="ready" @selected($i->status === 'ready')>Ready to post</option><option value="skipped">Skip it</option></select>
                    <textarea name="copy" rows="3" placeholder="Post text, ready to copy and paste" class="border rounded px-2 py-1 md:col-span-4">{{ $i->copy }}</textarea>
                    <textarea name="image_prompt" rows="2" placeholder="Image prompt (for an image tool). Make the image, then paste its public URL below." class="border rounded px-2 py-1 md:col-span-4">{{ $i->image_prompt }}</textarea>
                    <input name="image_alt" maxlength="300" value="{{ $i->image_alt }}" placeholder="Image description (alt text)" class="border rounded px-2 py-1 md:col-span-3">
                    <button class="px-3 py-1 bg-gray-800 text-white rounded md:col-span-1">Save</button>
                </form>
                <form method="POST" action="{{ route('content-plan.draft', $i->id) }}" onsubmit="return {{ $i->copy ? 'confirm(\'Replace the current text with a new AI draft?\')' : 'true' }}">
                    @csrf<button class="text-xs px-2 py-1 rounded border border-violet-300 text-violet-800 bg-violet-50">Draft text and image prompt with AI</button>
                    <span class="text-xs text-gray-500">Uses only the brand facts you have given the system. You review it before anything is posted.</span>
                </form>
                @if($i->status === 'failed' && $i->publish_error)
                    <p class="p-2 rounded bg-red-50 border border-red-200 text-xs text-red-800">Auto-post failed: {{ $i->publish_error }}</p>
                @endif
                @if(in_array($i->platform, ['facebook', 'instagram'], true))
                    <form method="POST" action="{{ route('content-plan.schedule', $i->id) }}" class="flex flex-wrap gap-2 items-center p-2 rounded bg-sky-50 border border-sky-100">
                        @csrf
                        <span class="text-xs text-sky-900">Post it for me:</span>
                        <input type="datetime-local" name="scheduled_at" required class="border rounded px-2 py-1">
                        <input type="url" name="media_url" placeholder="{{ $i->platform === 'instagram' ? 'Public image URL (required)' : 'Image URL (optional)' }}" class="border rounded px-2 py-1 min-w-[12rem] flex-1">
                        @if($i->platform === 'facebook')<input type="url" name="link_url" placeholder="Link (optional)" class="border rounded px-2 py-1 min-w-[10rem]">@endif
                        <button class="px-3 py-1 bg-sky-700 text-white rounded disabled:opacity-40" @disabled(!$autoOn) title="{{ $autoOn ? 'You are approving this exact text to be posted' : 'Auto-posting is off (META_PUBLISHING_ENABLED)' }}">Approve and schedule</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('content-plan.posted', $i->id) }}" class="flex flex-wrap gap-2 items-center">
                    @csrf
                    <input type="url" name="post_url" placeholder="Link to your post (optional)" class="border rounded px-2 py-1 flex-1 min-w-[14rem]">
                    <input type="date" name="posted_on" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1">
                    <button class="px-3 py-1 bg-emerald-600 text-white rounded">I posted this</button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- Posted --}}
    @if($posted->isNotEmpty())
        <div class="space-y-3">
            <h2 class="font-semibold text-gray-900">Posted ({{ $posted->count() }})</h2>
            @foreach($posted as $i)
                <div class="bg-white border rounded-lg p-4 text-sm">
                    <p class="font-medium text-gray-900">{{ $i->title }}
                        <span class="text-gray-400 font-normal">· {{ $platforms[$i->platform] ?? 'platform not set' }} · {{ $i->posted_at?->format('Y-m-d') }}</span>
                        @if($i->post_url)<a href="{{ preg_match('#^https?://#i', $i->post_url) ? $i->post_url : '#' }}" rel="noopener" target="_blank" class="text-blue-600 text-xs ml-1">open</a>@endif</p>
                    <form method="POST" action="{{ route('content-plan.results', $i->id) }}" class="mt-2 flex flex-wrap gap-2 items-center">
                        @csrf
                        @foreach(['impressions' => 'Reach / impressions', 'engagements' => 'Engagements (likes, comments, shares, saves)', 'clicks' => 'Link clicks', 'shares' => 'Shares'] as $f => $l)
                            <input type="number" min="0" name="{{ $f }}" value="{{ $i->$f }}" placeholder="{{ $l }}" title="{{ $l }}" class="border rounded px-2 py-1 w-40">
                        @endforeach
                        <button class="px-3 py-1 bg-blue-600 text-white rounded">Save results</button>
                        @if($i->measured_at)<span class="text-xs text-gray-400">updated {{ $i->measured_at->diffForHumans() }}</span>@else<span class="text-xs text-amber-700">not measured yet</span>@endif
                    </form>
                    <p class="mt-2 text-xs">
                        @if($i->site_measured_at)
                            <span class="text-gray-700">On the website: <strong>{{ number_format($i->site_sessions) }}</strong> visits · <strong>{{ number_format($i->site_key_events) }}</strong> sign-up/key events</span>
                            <span class="text-gray-400">(GA4, {{ $i->site_measured_at->diffForHumans() }})</span>
                        @elseif($i->link_url && str_contains($i->link_url, 'utm_campaign=vumbiai-'))
                            <span class="text-gray-500">Website visits appear here about 2 days after posting.</span>
                        @else
                            <span class="text-gray-500">No tracked link, so website visits cannot be measured for this post. Posts approved here get one automatically.</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Own idea --}}
    <div class="bg-white border rounded-lg p-4">
        <h2 class="font-semibold text-gray-900 mb-2">Add your own idea</h2>
        <form method="POST" action="{{ route('content-plan.store') }}" class="grid md:grid-cols-4 gap-2 text-sm">
            @csrf
            <input name="title" required minlength="3" maxlength="200" placeholder="What will you post about?" class="border rounded px-2 py-1 md:col-span-2">
            <input name="topic" maxlength="80" placeholder="Topic (for tracking what works)" class="border rounded px-2 py-1">
            <select name="persona_id" class="border rounded px-2 py-1"><option value="">For which persona? (optional)</option>@foreach($personas as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
            <select name="platform" class="border rounded px-2 py-1"><option value="">Platform</option>@foreach($platforms as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            <select name="format" class="border rounded px-2 py-1"><option value="">Format</option>@foreach($formats as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            <button class="px-3 py-1 bg-blue-600 text-white rounded">Add</button>
        </form>
    </div>
</div>
@endsection
