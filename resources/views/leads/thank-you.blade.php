@extends('layouts.guest')

@section('title', 'Thank You')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 py-16">
    <div class="max-w-md w-full text-center">
        <div class="text-6xl mb-6">✅</div>
        <h1 class="text-3xl font-bold text-white mb-3">Thank you</h1>
        <p class="text-slate-400 leading-relaxed">
            We've received your updated information. A member of our team will be in touch shortly.
        </p>
        <a href="{{ url('/') }}"
           class="inline-block mt-8 px-6 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white font-semibold transition">
            Back to Home
        </a>
    </div>
</div>
@endsection