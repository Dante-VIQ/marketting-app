{{-- resources/views/welcome.blade.php --}}
@extends('layouts.guest')

@section('title', 'Vumbi AI – Marketing that runs itself')

@section('content')
<div class="relative isolate overflow-hidden bg-slate-950 text-slate-100">
    
    <!-- Ambient Gradient Backdrops -->
    <div class="absolute -top-40 left-1/2 -z-10 transform-gpu blur-3xl sm:-top-80" aria-hidden="true">
        <div class="aspect-[1097/845] w-[68.5625rem] bg-gradient-to-tr from-[#10b981] via-[#6366f1] to-[#a855f7] opacity-20" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
    </div>

    {{-- Hero Section --}}
    <section class="relative px-6 pt-20 pb-24 sm:pt-32 sm:pb-32 lg:px-8">
        <div class="mx-auto max-w-4xl text-center">
            
            <!-- Badges -->
            <div class="mb-8 flex flex-wrap items-center justify-center gap-3">
                <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-3.5 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    🏆 Agents for Humans 2026
                </span>
                <span class="inline-flex items-center rounded-full bg-indigo-500/10 px-3.5 py-1 text-xs font-medium text-indigo-400 ring-1 ring-inset ring-indigo-500/20">
                    ⚡ Saves 10+ hrs/week
                </span>
            </div>

            <!-- Main Heading -->
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-white">
                Marketing that <br/>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-indigo-400 bg-clip-text text-transparent">runs itself.</span>
            </h1>

            <p class="mt-6 text-lg leading-8 text-slate-300 max-w-2xl mx-auto">
                Vumbi AI is an autonomous hierarchical agent for solo founders. It monitors your business, surfaces opportunities, and executes tasks—only bringing you in when human judgment is needed.
            </p>

            <!-- Call to Actions -->
            <div class="mt-10 flex items-center justify-center gap-x-4">
                <a href="{{ route('register') }}" class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:from-emerald-400 hover:to-teal-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 transition-all duration-200">
                    Get Started Free
                </a>
                <a href="https://github.com/Dante-VIQ/Autonomous-Agent" target="_blank" class="rounded-xl bg-slate-900 border border-slate-800 px-6 py-3.5 text-sm font-semibold text-slate-300 hover:text-white hover:border-slate-700 hover:bg-slate-800/50 transition duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64 7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                    View Source <span aria-hidden="true">→</span>
                </a>
            </div>
        </div>

        {{-- Terminal Preview --}}
        <div class="mx-auto mt-16 max-w-3xl rounded-2xl border border-slate-800 bg-slate-900/90 p-4 shadow-2xl backdrop-blur-xl relative overflow-hidden">
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl"></div>
            
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4 px-2">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
                    <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
                    <span class="h-3 w-3 rounded-full bg-emerald-500/80"></span>
                    <span class="ml-2 text-xs font-mono text-slate-400">vumbi-agent@autonomous:~</span>
                </div>
                <span class="text-xs font-mono text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">LIVE AGENT LOOP</span>
            </div>
            
            <div class="space-y-2 font-mono text-xs sm:text-sm p-2 text-slate-300">
                <div class="text-slate-500"># Initializing autonomous discovery sequence...</div>
                <div class="flex items-center gap-2"><span class="text-indigo-400">🔄 CYCLE</span> <span>AGENT CYCLE — Brand 1</span></div>
                <div class="flex items-center gap-2"><span class="text-emerald-400">📡 DISCOVERY</span> <span>Found 6 pending opportunities</span></div>
                <div class="text-slate-400 pl-4">⚙️ Processing opportunities against safety policy...</div>
                <div class="pl-6 text-emerald-300 bg-emerald-950/30 py-1 px-2 rounded border-l-2 border-emerald-400">
                    → Content Gap Identified: "Complete Guide to Maasai Mara Safari"
                </div>
                <div class="pl-6 text-amber-300 flex items-center gap-2">
                    <span>🧠 Gemini Reasoning</span> 
                    <span class="text-xs bg-amber-500/10 text-amber-400 px-1.5 py-0.5 rounded border border-amber-500/20">Confidence: 0.85</span>
                </div>
                <div class="text-indigo-300 pl-4">⚡ Action Executed: Draft scheduled in CMS (Awaiting Human Approval)</div>
                <div class="pt-2 text-emerald-400 font-semibold">✅ CYCLE COMPLETE – Processed: 6 tasks (0 errors)</div>
            </div>
        </div>
    </section>

    {{-- How It Works / Features --}}
    <section class="border-t border-slate-800/80 bg-slate-900/40 px-6 py-24 sm:py-32 lg:px-8 backdrop-blur-sm">
        <div class="mx-auto max-w-7xl">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">How Vumbi AI works</h2>
                <p class="mt-4 text-slate-400">Four integrated steps powering continuous, safe marketing automation.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                
                <!-- Step 1 -->
                <div class="bg-slate-900/60 p-8 rounded-2xl border border-slate-800 hover:border-emerald-500/40 transition duration-300 shadow-xl group">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition duration-300">
                        🔍
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">1. Monitor</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Continuous scans for SEO vulnerabilities, lead falloffs, content gaps, and ad performance.</p>
                </div>

                <!-- Step 2 -->
                <div class="bg-slate-900/60 p-8 rounded-2xl border border-slate-800 hover:border-indigo-500/40 transition duration-300 shadow-xl group">
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition duration-300">
                        🧠
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">2. Decide</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Leverages Gemini reasoning combined with past memory to craft optimal strategies.</p>
                </div>

                <!-- Step 3 -->
                <div class="bg-slate-900/60 p-8 rounded-2xl border border-slate-800 hover:border-amber-500/40 transition duration-300 shadow-xl group">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition duration-300">
                        ⚡
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">3. Execute</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Autonomously performs low-risk tasks while queueing high-impact actions for approval.</p>
                </div>

                <!-- Step 4 -->
                <div class="bg-slate-900/60 p-8 rounded-2xl border border-slate-800 hover:border-teal-500/40 transition duration-300 shadow-xl group">
                    <div class="w-12 h-12 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition duration-300">
                        📈
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">4. Learn</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Logs success metrics to refine future autonomous decisions and adapt to your audience.</p>
                </div>

            </div>
        </div>
    </section>

    {{-- Bottom Call-to-Action --}}
    <section class="relative px-6 py-24 sm:py-32 lg:px-8 border-t border-slate-800/80 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950 via-indigo-950/20 to-slate-950 -z-10"></div>
        <div class="mx-auto max-w-4xl text-center">
            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                Ready to reclaim your time?
            </h2>
            <p class="mt-4 text-lg leading-8 text-slate-300 max-w-xl mx-auto">
                Vumbi AI is open-source and self-hostable. Deploy your autonomous agent and start saving 10+ hours every week.
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="https://github.com/Dante-VIQ/Autonomous-Agent" target="_blank" class="rounded-xl bg-white px-8 py-3.5 text-sm font-semibold text-slate-950 shadow-lg hover:bg-slate-100 transition duration-200">
                    Get Started on GitHub
                </a>
                <a href="{{ route('register') }}" class="rounded-xl bg-slate-900 border border-slate-800 px-8 py-3.5 text-sm font-semibold text-slate-300 hover:text-white hover:border-slate-700 transition duration-200">
                    Create Account
                </a>
            </div>
        </div>
    </section>

</div>
@endsection