@extends('layouts.app')

@section('title', 'Buyer Personas')

@php
    $levelStyle = ['hypothesis' => 'bg-amber-100 text-amber-800', 'emerging' => 'bg-blue-100 text-blue-800', 'validated' => 'bg-emerald-100 text-emerald-800'];
    $levelLabel = ['hypothesis' => 'Hypothesis', 'emerging' => 'Emerging', 'validated' => 'Validated'];
    $lists = ['goals' => 'Goals / jobs to be done', 'triggers' => 'Triggers: what makes them start looking', 'pains' => 'Pains (most painful first)',
              'objections' => 'Objections: why they hesitate', 'motivations' => 'Motivations & values (psychographics)', 'channels' => 'Where they look for information'];
    $roles = ['decision_maker' => 'Decision maker', 'influencer' => 'Influencer', 'user' => 'End user', 'gatekeeper' => 'Gatekeeper'];
    $kinds = \App\Models\PersonaEvidence::KINDS;
@endphp

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">🧑‍🤝‍🧑 Buyer Personas</h1>
        <p class="text-gray-600">Who {{ $brand->name }} is really talking to: their jobs, triggers, pains, objections and motivations. Personas start as hypotheses and earn trust as you add what customers actually said.</p>
    </div>

    @if(session('message'))<div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('message') }}</div>@endif
    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    {{-- Lead data: a starting point, not proof --}}
    <div class="p-4 bg-white border rounded-lg text-sm">
        <h2 class="font-semibold text-gray-900">What your real leads look like</h2>
        @if($leads['total'] === 0)
            <p class="text-gray-600 mt-1">No leads recorded yet, so there is nothing to infer from. Start with a few customer conversations instead.</p>
        @else
            <p class="text-gray-600 mt-1">{{ $leads['total'] }} leads on record. Use these patterns to form hypotheses, then confirm them with customers.</p>
            <div class="mt-2 flex flex-wrap gap-6 text-gray-700">
                @foreach(['by_category' => 'Top categories', 'by_source' => 'Top sources'] as $k => $label)
                    @if(!empty($leads[$k]))
                        <div><p class="text-xs uppercase text-gray-500">{{ $label }}</p>
                            @foreach($leads[$k] as $row)<p>{{ $row['label'] }} <span class="text-gray-400">({{ $row['count'] }})</span></p>@endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    @forelse($personas as $row)
        @php $p = $row['model']; $c = $row['confidence']; @endphp
        <div class="bg-white border rounded-lg p-5 space-y-4 {{ $p->is_active ? '' : 'opacity-60' }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $p->name }}@if($p->role_title) <span class="text-gray-500 font-normal">· {{ $p->role_title }}</span>@endif</h2>
                    @if($p->summary)<p class="text-sm text-gray-600">{{ $p->summary }}</p>@endif
                    @if($p->quote)<p class="text-sm italic text-gray-500 mt-1">“{{ $p->quote }}”</p>@endif
                </div>
                <div class="text-right">
                    <span class="px-2 py-1 rounded-full text-xs {{ $levelStyle[$c['level']] }}">{{ $levelLabel[$c['level']] }}</span>
                    @if($c['stale'])<span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">Evidence is old</span>@endif
                    <p class="text-xs text-gray-500 mt-1">{{ $c['direct'] }} direct · {{ $c['inferred'] }} inferred</p>
                </div>
            </div>
            <p class="text-xs text-gray-600">{{ $c['next_step'] }}</p>

            <div class="grid md:grid-cols-3 gap-4 text-sm">
                @foreach($lists as $field => $label)
                    @if(!empty($p->$field))
                        <div><p class="text-xs uppercase text-gray-500">{{ $label }}</p>
                            <ul class="list-disc ml-4 text-gray-700">@foreach($p->$field as $item)<li>{{ $item }}</li>@endforeach</ul>
                        </div>
                    @endif
                @endforeach
            </div>

            <details class="text-sm">
                <summary class="cursor-pointer text-blue-700">Evidence ({{ $p->evidence->count() }})</summary>
                <ul class="mt-2 space-y-1">
                    @foreach($p->evidence->sortByDesc('observed_on') as $e)
                        <li class="flex justify-between gap-2"><span><strong>{{ $kinds[$e->kind] ?? $e->kind }}</strong>@if($e->observed_on) <span class="text-gray-400">{{ $e->observed_on->format('Y-m-d') }}</span>@endif: {{ $e->note }}</span>
                            <form method="POST" action="{{ route('personas.evidence.destroy', [$p->id, $e->id]) }}">@csrf @method('DELETE')<button class="text-xs text-red-600">remove</button></form></li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('personas.evidence.store', $p->id) }}" class="mt-3 flex flex-wrap gap-2">
                    @csrf
                    <select name="kind" class="border rounded px-2 py-1">@foreach($kinds as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    <input type="date" name="observed_on" max="{{ now()->toDateString() }}" class="border rounded px-2 py-1">
                    <input name="note" required maxlength="500" placeholder="What did you learn? e.g. “said price transparency decided it”" class="border rounded px-2 py-1 flex-1 min-w-[16rem]">
                    <button class="px-3 py-1 bg-blue-600 text-white rounded">Add</button>
                </form>
            </details>

            <details class="text-sm">
                <summary class="cursor-pointer text-gray-600">Edit persona</summary>
                @include('personas._form', ['action' => route('personas.update', $p->id), 'method' => 'PUT', 'p' => $p])
                <form method="POST" action="{{ route('personas.destroy', $p->id) }}" onsubmit="return confirm('Delete this persona and its evidence?')" class="mt-2">@csrf @method('DELETE')<button class="text-xs text-red-600">Delete persona</button></form>
            </details>
        </div>
    @empty
        <div class="p-5 rounded-lg bg-blue-50 border border-blue-200">
            <h2 class="font-semibold text-blue-900">No personas yet</h2>
            <p class="text-sm text-blue-800 mt-1">Start with one or two. Describe what the person is trying to get done, what pushes them to look, what worries them, and where they research. Then add real customer quotes as evidence.</p>
        </div>
    @endforelse

    <div class="bg-white border rounded-lg p-5">
        <h2 class="font-semibold text-gray-900 mb-2">Add a persona</h2>
        @include('personas._form', ['action' => route('personas.store'), 'method' => 'POST', 'p' => null])
    </div>
</div>
@endsection
