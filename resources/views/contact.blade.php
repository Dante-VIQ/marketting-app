@extends('layouts.guest')

@section('title', 'Contact Revora')
@section('meta_description', 'Get in touch with the Revora team. Questions about the platform, partnerships, or the Agents for Humans hackathon entry.')

@section('content')
<div class="relative isolate overflow-hidden bg-slate-950 py-20 sm:py-32 text-slate-100">
    <div class="absolute -top-40 left-1/2 -z-10 transform-gpu -translate-x-1/2 blur-3xl sm:-top-80" aria-hidden="true">
        <div class="aspect-[1097/845] w-[68.5rem] bg-gradient-to-tr from-[#10b981] via-[#6366f1] to-[#a855f7] opacity-20"
             style="clip-path:polygon(74.1% 44.1%,100% 61.6%,97.5% 26.9%,85.5% 0.1%,80.7% 2%,72.5% 32.5%,60.2% 62.4%,52.4% 68.1%,47.5% 58.3%,45.2% 34.5%,27.5% 76.7%,0.1% 64.9%,17.9% 100%,27.6% 76.8%,76.1% 97.7%,74.1% 44.1%)"></div>
    </div>

    <div class="mx-auto max-w-7xl px-6 lg:px-8">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-3.5 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20 mb-4">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Get in touch
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-white">
                Questions? Ideas?<br>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-indigo-400 bg-clip-text text-transparent">We'd love to hear from you.</span>
            </h1>
            <p class="mt-6 text-lg leading-8 text-slate-300">
                Whether you're evaluating Revora, want to collaborate, or just want to know how the AI visibility score works — drop us a line.
            </p>
        </div>

        {{-- ── Grid ────────────────────────────────────────────────────── --}}
        <div class="mx-auto mt-16 max-w-6xl grid grid-cols-1 gap-12 lg:grid-cols-12 items-start">

            {{-- Form --}}
            <div class="lg:col-span-7 rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl shadow-2xl">
                <h2 class="text-2xl font-bold text-white mb-2">Send a message</h2>
                <p class="text-sm text-slate-400 mb-8">We reply within one business day.</p>

                @if(session('success'))
                    <div class="mb-6 rounded-xl bg-emerald-500/10 border border-emerald-500/30 p-4 text-sm text-emerald-300 flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <form action="{{ route('contact.submit') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-2">Your name</label>
                        <input type="text" name="name" id="name" required placeholder="Ada Lovelace"
                               value="{{ old('name') }}"
                               class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition text-sm @error('name') border-rose-500 @enderror">
                        @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email address</label>
                        <input type="email" name="email" id="email" required placeholder="ada@example.com"
                               value="{{ old('email') }}"
                               class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition text-sm @error('email') border-rose-500 @enderror">
                        @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-slate-300 mb-2">Message</label>
                        <textarea name="message" id="message" rows="5" required placeholder="Tell us what you're building or what you'd like to know…"
                                  class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition text-sm resize-none @error('message') border-rose-500 @enderror">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:from-emerald-400 hover:to-teal-500 transition-all duration-200">
                        Send message →
                    </button>
                </form>
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-5 space-y-6">

                <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl">
                    <h2 class="text-xl font-bold text-white mb-6">Direct channels</h2>
                    <div class="space-y-2">
                        @foreach ([
                            ['href'=>'mailto:dante@vumbiventures.com', 'color'=>'emerald', 'icon'=>'email',   'label'=>'Email us',           'value'=>'dante@vumbiventures.com'],
                            ['href'=>'https://github.com/Dante-VIQ/Autonomous-Agent', 'color'=>'indigo', 'icon'=>'github', 'label'=>'GitHub (open source)', 'value'=>'Dante-VIQ/Autonomous-Agent'],
                            ['href'=>'https://agentsforhumans.devpost.com', 'color'=>'amber', 'icon'=>'trophy', 'label'=>'Hackathon entry', 'value'=>'Agents for Humans 2026'],
                        ] as $c)
                        <a href="{{ $c['href'] }}" target="{{ str_starts_with($c['href'],'http') ? '_blank' : '' }}"
                           class="group flex items-center gap-4 p-3 rounded-xl hover:bg-slate-800/50 transition">
                            <div class="rounded-lg bg-{{ $c['color'] }}-500/10 p-2.5 text-{{ $c['color'] }}-400 border border-{{ $c['color'] }}-500/20 group-hover:border-{{ $c['color'] }}-500/40 transition shrink-0">
                                @if($c['icon']==='email')
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                @elseif($c['icon']==='github')
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg>
                                @endif
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-400">{{ $c['label'] }}</div>
                                <div class="text-sm font-semibold text-slate-200 group-hover:text-{{ $c['color'] }}-400 transition">{{ $c['value'] }}</div>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>

                {{-- Free scan nudge --}}
                <div class="rounded-2xl bg-gradient-to-br from-emerald-950/60 to-slate-900/60 border border-emerald-800/40 p-6 text-center">
                    <div class="text-2xl mb-2">🔍</div>
                    <h3 class="font-semibold text-white mb-1">Not sure where to start?</h3>
                    <p class="text-sm text-slate-400 mb-4">Run a free AI visibility scan on your website — no account needed.</p>
                    <a href="{{ route('public.scanner.index') }}"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition-colors">
                        Scan my site →
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
