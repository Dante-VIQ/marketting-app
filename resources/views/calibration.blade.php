@extends('layouts.studio')

@section('title', 'Calibration Dashboard — Vumbi AI')

@section('content')
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
    <div class="mb-10">
        <span class="text-[11px] font-mono tracking-[0.2em] text-emerald-400/80 uppercase">Intelligence</span>
        <h1 class="mt-2 text-4xl font-bold text-white">Confidence Calibration</h1>
        <p class="mt-3 text-slate-400 max-w-2xl">
            How well-calibrated is the agent's confidence? A well-calibrated agent says 0.80 and is right 80% of the time.
            This dashboard shows the measured drift.
        </p>
    </div>

    @livewire('calibration-dashboard')
</section>
@endsection