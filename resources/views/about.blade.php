{{-- resources/views/about.blade.php --}}
@extends('layouts.guest')

@section('title', 'About Vumbi AI')

@section('content')
<div class="relative isolate overflow-hidden bg-slate-950 py-10 sm:py-22 text-slate-100">
    <!-- Gradient Ambient Backgrounds -->
    <div class="absolute -top-40 left-1/2 -z-10 transform-gpu blur-3xl sm:-top-80" aria-hidden="true">
        <div class="aspect-[1097/845] w-[68.5625rem] bg-gradient-to-tr from-[#6366f1] to-[#a855f7] opacity-20" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
    </div>

    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <!-- Hero Header -->
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center rounded-full bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-400 ring-1 ring-inset ring-indigo-500/20 mb-4">
                Autonomous Marketing Intelligence
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-white">
                Focus on building. <br/>
                <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent">Let AI handle the noise.</span>
            </h1>
            <p class="mt-6 text-lg leading-8 text-slate-300">
                Vumbi AI is a hierarchical multi-agent system that autonomously manages your marketing busywork—so solo founders and small teams can scale without burning out.
            </p>
        </div>

        <!-- Problem vs Solution Cards -->
        <div class="mx-auto mt-16 max-w-5xl grid grid-cols-1 gap-8 md:grid-cols-2">
            <!-- Problem Card -->
            <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-x-3 text-red-400 font-semibold text-lg mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        The Problem
                    </div>
                    <p class="text-slate-300 leading-relaxed">
                        Solo founders spend over <strong class="text-white">30% of their working hours</strong> on repetitive task cycles—SEO checks, lead follow-ups, content creation, and campaign monitoring. Essential work, but a massive drain on time, energy, and creative momentum.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-800/80 text-sm text-slate-400 font-medium">
                    ⚡ Result: Burnout & slow product growth.
                </div>
            </div>

            <!-- Solution Card -->
            <div class="rounded-2xl bg-gradient-to-b from-indigo-950/40 to-slate-900/60 border border-indigo-500/30 p-8 backdrop-blur-xl shadow-xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl"></div>
                <div>
                    <div class="flex items-center gap-x-3 text-indigo-400 font-semibold text-lg mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        The Solution
                    </div>
                    <p class="text-slate-300 leading-relaxed">
                        Vumbi AI monitors your business data via Laravel API, reasons through opportunities using Gemini (or self-hosted Ollama), and enforces a strict safety policy—executing actions automatically and calling you in only when critical human judgment is required.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-indigo-900/50 text-sm text-indigo-300 font-medium">
                    ✨ Result: Autonomous execution with full control.
                </div>
            </div>
        </div>

        <!-- Target Audience Section -->
        <div class="mx-auto mt-20 max-w-5xl">
            <h2 class="text-2xl font-bold text-center text-white mb-10">Designed specifically for lean builders</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="rounded-xl bg-slate-900/40 border border-slate-800/80 p-5 hover:border-slate-700 transition">
                    <div class="text-2xl mb-2">🚀</div>
                    <h3 class="font-semibold text-white">Solo Founders</h3>
                    <p class="mt-1 text-sm text-slate-400">Reclaim 10+ hours every week to focus on building products.</p>
                </div>
                <div class="rounded-xl bg-slate-900/40 border border-slate-800/80 p-5 hover:border-slate-700 transition">
                    <div class="text-2xl mb-2">💼</div>
                    <h3 class="font-semibold text-white">Small Agencies</h3>
                    <p class="mt-1 text-sm text-slate-400">Scale operations & manage multiple client workflows seamlessly.</p>
                </div>
                <div class="rounded-xl bg-slate-900/40 border border-slate-800/80 p-5 hover:border-slate-700 transition">
                    <div class="text-2xl mb-2">🌱</div>
                    <h3 class="font-semibold text-white">Bootstrappers</h3>
                    <p class="mt-1 text-sm text-slate-400">Access enterprise-level AI capabilities without the enterprise cost.</p>
                </div>
                <div class="rounded-xl bg-slate-900/40 border border-slate-800/80 p-5 hover:border-slate-700 transition">
                    <div class="text-2xl mb-2">🛠️</div>
                    <h3 class="font-semibold text-white">Developers</h3>
                    <p class="mt-1 text-sm text-slate-400">Extend, hack, and customize open-source agent architecture.</p>
                </div>
            </div>
        </div>

        <!-- Tech Stack & Creator Split -->
        <div class="mx-auto mt-20 max-w-5xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Tech Stack (7 Cols) -->
            <div class="lg:col-span-7 rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    Technology Stack
                </h2>
                <dl class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-slate-800">
                        <dt class="text-sm font-medium text-slate-400">Orchestration</dt>
                        <dd class="text-sm font-semibold text-indigo-300">Strands Agents SDK (Python)</dd>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-800">
                        <dt class="text-sm font-medium text-slate-400">AI Reasoning</dt>
                        <dd class="text-sm font-semibold text-indigo-300">Google Gemini / Ollama</dd>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-slate-800">
                        <dt class="text-sm font-medium text-slate-400">Backend API</dt>
                        <dd class="text-sm font-semibold text-indigo-300">Laravel 13 & MySQL</dd>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <dt class="text-sm font-medium text-slate-400">Runtime Environment</dt>
                        <dd class="text-sm font-semibold text-indigo-300">Python 3.10+</dd>
                    </div>
                </dl>
            </div>

            <!-- Meet the Creator (5 Cols) -->
            <div class="lg:col-span-5 rounded-2xl bg-gradient-to-br from-slate-900/90 to-indigo-950/50 border border-indigo-500/20 p-8 backdrop-blur-xl relative">
                <h2 class="text-xl font-bold text-white mb-4">Meet the Creator</h2>
                <p class="text-sm text-slate-300 leading-relaxed">
                    <strong class="text-white">Dante</strong> — self-taught developer & founder of Vumbi Ventures. Built Vumbi AI to solve his own growth bottlenecks. No fancy CS degree, just shipped code that works.
                </p>
                <blockquote class="mt-6 border-l-2 border-indigo-500 pl-4 italic text-xs text-indigo-200 leading-normal">
                    "I built Vumbi AI because I was spending 15 hours a week on marketing tasks that didn't grow my business. Now the agent does the work, and I focus on building."
                </blockquote>
            </div>
        </div>
    </div>
</div>
@endsection