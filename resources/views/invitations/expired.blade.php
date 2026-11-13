@extends('layouts.app')

@section('title', 'Invitation Expired')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <div class="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-white mb-2">Invitation Expired</h1>
        <p class="text-slate-400 mb-6">
            This invitation to join <strong class="text-white">{{ $invitation->brand->name }}</strong>
            expired on {{ $invitation->expires_at->format('D, d M Y') }}.
            Ask <strong class="text-white">{{ $invitation->inviter->name }}</strong> to send you a new one.
        </p>

        <a href="{{ route('login') }}"
           class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold px-5 py-2.5 rounded-lg text-sm transition-colors">
            Go to Login
        </a>
    </div>
</div>
@endsection
