@extends('layouts.guest')

@section('title', 'Create Account')

@section('content')
<div class="relative min-h-screen flex items-center justify-center px-4 py-16">

    {{-- Ambient glow --}}
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-1/4 left-1/4 w-80 h-80 bg-emerald-500/8 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-indigo-500/8 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20 mb-4">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Join Vumbi AI
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Create your account</h1>
            <p class="mt-2 text-sm text-slate-400">Your AI marketing co-pilot awaits.</p>
        </div>

        {{-- Card --}}
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/60 rounded-2xl shadow-2xl p-8">

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Full Name</label>
                    <input id="name" name="name" type="text" autocomplete="name" required
                           value="{{ old('name') }}"
                           placeholder="Jane Kamau"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60
                                  transition-colors duration-150">
                    @error('name')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Email Address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           placeholder="you@yourcompany.com"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60
                                  transition-colors duration-150">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                           placeholder="At least 8 characters"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60
                                  transition-colors duration-150">
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                           placeholder="Repeat password"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60
                                  transition-colors duration-150">
                </div>

                {{-- Submit --}}
                <div class="pt-2">
                    <button type="submit"
                            class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-sm font-semibold
                                   shadow-lg shadow-emerald-500/20 hover:from-emerald-400 hover:to-teal-500
                                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900
                                   transition-all duration-200">
                        Create Account
                    </button>
                </div>

            </form>
        </div>

        {{-- Footer link --}}
        <p class="text-center mt-5 text-sm text-slate-500">
            Already have an account?
            <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Sign in →</a>
        </p>

    </div>
</div>
@endsection
