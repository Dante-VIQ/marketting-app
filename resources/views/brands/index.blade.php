@extends('layouts.app')

@section('title', 'Brand Management')

@section('content')

@if($brands->isEmpty())

    {{-- ── First-time user: show the onboarding wizard ────────────── --}}
    <div class="min-h-[80vh] flex flex-col justify-center">

        <div class="text-center mb-2">
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Welcome to Vumbi AI</h1>
            <p class="mt-2 text-slate-400 text-sm">Let's set up your first brand in 3 quick steps.</p>
        </div>

        @livewire('brand-onboarding')

    </div>

@else

    {{-- ── Returning user: standard brand manager ─────────────────── --}}
    @if(session('onboarding_done'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-3 text-sm text-emerald-400 mb-4">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><strong>{{ session('onboarding_done') }}</strong> is ready. Your AI brief will arrive at 6 AM tomorrow.</span>
            </div>
        </div>
    @endif

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-slate-900/60 border border-slate-800/60 rounded-2xl shadow-xl overflow-hidden">
                <div class="p-6">
                    @livewire('brand-manager')
                </div>
            </div>
        </div>
    </div>

@endif

@endsection
