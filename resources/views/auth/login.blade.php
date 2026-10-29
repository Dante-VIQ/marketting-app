@extends('layouts.guest')

@section('title', 'Sign In')

@section('content')
<div class="relative min-h-screen flex items-center justify-center px-4 py-16">

    {{-- Ambient glow --}}
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-1/4 right-1/4 w-80 h-80 bg-emerald-500/8 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/4 w-80 h-80 bg-indigo-500/8 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-x-1.5 rounded-full bg-slate-800/80 px-3 py-1 text-xs font-medium text-slate-300 ring-1 ring-inset ring-slate-700/60 mb-4">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                Welcome back
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Sign in to Vumbi AI</h1>
            <p class="mt-2 text-sm text-slate-400">Your autonomous marketing system is ready.</p>
        </div>

        {{-- Status message --}}
        @if(session('status'))
            <div class="mb-5 flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-4 py-3 text-sm text-emerald-400">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('status') }}
            </div>
        @endif

        {{-- Card --}}
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/60 rounded-2xl shadow-2xl p-8">

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

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
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Password</label>
                        @if(Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-xs text-emerald-400 hover:text-emerald-300 transition-colors">
                                Forgot password?
                            </a>
                        @endif
                    </div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           placeholder="Your password"
                           class="w-full px-4 py-3 rounded-xl bg-slate-800/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500/60
                                  transition-colors duration-150">
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center gap-2">
                    <input id="remember" name="remember" type="checkbox"
                           class="h-4 w-4 rounded bg-slate-800 border-slate-600 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-slate-900">
                    <label for="remember" class="text-sm text-slate-400 select-none">Remember me</label>
                </div>

                {{-- Submit --}}
                <div class="pt-1">
                    <button type="submit"
                            class="w-full py-3 px-6 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-sm font-semibold
                                   shadow-lg shadow-emerald-500/20 hover:from-emerald-400 hover:to-teal-500
                                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-slate-900
                                   transition-all duration-200">
                        Sign In
                    </button>
                </div>

                {{-- Passkey --}}
                <div x-data="{ ok: !!(window.Passkeys && window.Passkeys.isSupported()), busy: false, error: '' }"
                     x-show="ok" x-cloak class="pt-1">
                    <div class="relative my-3 flex items-center">
                        <div class="flex-grow border-t border-slate-800"></div>
                        <span class="mx-3 text-xs text-slate-500 shrink-0">or</span>
                        <div class="flex-grow border-t border-slate-800"></div>
                    </div>
                    <button type="button" :disabled="busy"
                            @click="busy = true; error = '';
                                    window.Passkeys.verify({ remember: document.getElementById('remember').checked })
                                      .then(r => { window.location.href = (r && r.redirect) || '{{ url('/dashboard') }}'; })
                                      .catch(e => { error = (e && e.message) ? e.message : 'Passkey sign-in did not work. Use your password instead.'; busy = false; })"
                            class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl
                                   bg-slate-800/60 border border-slate-700/60 text-sm font-medium text-slate-300
                                   hover:text-white hover:border-slate-600 disabled:opacity-50
                                   transition-all duration-150">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        <span x-text="busy ? 'Waiting for your device…' : 'Sign in with a passkey'"></span>
                    </button>
                    <p x-show="error" x-text="error" class="mt-2 text-xs text-red-400 text-center"></p>
                </div>

            </form>
        </div>

        {{-- Footer link --}}
        @if(Route::has('register'))
            <p class="text-center mt-5 text-sm text-slate-500">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Create one →</a>
            </p>
        @endif

    </div>
</div>
@endsection
