@extends('layouts.app')

@section('title', 'Recurring Issues — Vumbi AI')

@section('content')
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16">
    <div class="mb-10">
        <span class="text-[11px] font-mono tracking-[0.2em] text-amber-400/80 uppercase">Escalations</span>
        <h1 class="mt-2 text-4xl font-bold text-white">Recurring Issues</h1>
        <p class="mt-3 text-slate-400 max-w-xl">
            The agent escalates here when it detects a problem that keeps recurring despite repeated attempts.
            Choose how you want the agent to proceed.
        </p>
    </div>

    @livewire('recurring-issues-panel')
</section>
@endsection