<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Free AI Visibility Check — Vumbi AI</title>
    <meta name="description" content="Paste your website URL and find out how visible your business is to AI search engines. Free, instant, no sign-up required.">
    @include('layouts.partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-950 text-slate-100 min-h-full" x-data>

    {{-- Ambient blurs --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 -left-24 w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[400px] h-[400px] bg-indigo-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 left-1/3 w-[400px] h-[400px] bg-sky-500/8 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 min-h-screen flex flex-col">

        {{-- Nav --}}
        <nav class="px-6 py-4 flex items-center justify-between max-w-5xl mx-auto w-full">
            <a href="{{ route('landing') }}" class="flex items-center space-x-1.5">
                <span class="text-xl font-bold text-emerald-400 tracking-tight">Vumbi</span>
                <span class="text-xl font-bold text-white">AI</span>
            </a>
            <div class="flex items-center space-x-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Dashboard →</a>
                @else
                    <a href="{{ route('login') }}" class="text-slate-400 hover:text-slate-200 transition-colors">Sign in</a>
                    <a href="{{ route('register') }}" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition-colors">Get started free</a>
                @endauth
            </div>
        </nav>

        {{-- Hero --}}
        <main class="flex-1 flex flex-col items-center justify-center px-4 py-16 sm:py-24">
            <div class="w-full max-w-2xl text-center">

                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-950/60 border border-emerald-800/50 text-emerald-300 text-xs font-semibold mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Free · No sign-up · Instant results
                </div>

                <h1 class="text-4xl sm:text-5xl font-extrabold text-white leading-tight mb-4">
                    Is your business<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-sky-400">visible to AI?</span>
                </h1>

                <p class="text-slate-400 text-lg mb-10 max-w-lg mx-auto">
                    AI assistants like ChatGPT and Gemini are the new search engines.
                    Paste your URL below and find out if they can find you.
                </p>

                {{-- Scan form --}}
                <form method="POST" action="{{ route('public.scanner.scan') }}"
                      x-data="{ loading: false, url: '' }"
                      @submit="loading = true"
                      class="w-full">
                    @csrf

                    @error('url')
                        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-950/60 border border-rose-800/50 text-rose-300 text-sm text-left">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="flex flex-col sm:flex-row gap-3">
                        <input
                            type="url"
                            name="url"
                            x-model="url"
                            placeholder="https://yourbusiness.com"
                            value="{{ old('url') }}"
                            required
                            class="flex-1 px-4 py-3.5 rounded-xl bg-slate-900/80 border border-slate-700/60 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-base transition-all"
                        >
                        <button
                            type="submit"
                            :disabled="loading || !url"
                            class="px-6 py-3.5 rounded-xl font-semibold text-white transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed
                                   bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400
                                   shadow-lg shadow-emerald-900/40"
                        >
                            <span x-show="!loading">Scan my website →</span>
                            <span x-show="loading" class="flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Scanning…
                            </span>
                        </button>
                    </div>

                    <p class="mt-3 text-xs text-slate-600">3 free scans per hour · Takes ~15 seconds · No account needed</p>
                </form>
            </div>

            {{-- What you'll get --}}
            <div class="mt-20 w-full max-w-3xl grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
                @foreach ([
                    ['icon' => '🎯', 'title' => 'Visibility score', 'desc' => 'How clearly your business is described for AI search engines, scored 0–100.'],
                    ['icon' => '🔍', 'title' => 'Content gaps', 'desc' => 'What AI assistants can\'t figure out about you — and why that costs you traffic.'],
                    ['icon' => '⚡', 'title' => '3 quick wins', 'desc' => 'Prioritised actions you can take this week to improve your AI presence.'],
                ] as $card)
                    <div class="p-4 rounded-xl bg-slate-900/40 border border-slate-800/60">
                        <div class="text-2xl mb-2">{{ $card['icon'] }}</div>
                        <div class="font-semibold text-slate-200 text-sm mb-1">{{ $card['title'] }}</div>
                        <div class="text-slate-500 text-xs leading-relaxed">{{ $card['desc'] }}</div>
                    </div>
                @endforeach
            </div>
        </main>

        {{-- Footer --}}
        <footer class="py-6 text-center text-xs text-slate-700">
            Built by <a href="https://vumbidna.com" class="text-slate-600 hover:text-slate-400 transition-colors">Vumbi AI</a>
            · <a href="{{ route('landing') }}" class="text-slate-600 hover:text-slate-400 transition-colors">Back to home</a>
        </footer>
    </div>

</body>
</html>
