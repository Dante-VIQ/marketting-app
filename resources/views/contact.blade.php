{{-- resources/views/contact.blade.php --}}
@extends('layouts.guest')

@section('title', 'Contact Vumbi AI')

@section('content')
<div class="relative isolate overflow-hidden bg-slate-950 py-20 sm:py-32 text-slate-100">
    <!-- Ambient Gradient Backdrops -->
    <div class="absolute -top-40 left-1/2 -z-10 transform-gpu blur-3xl sm:-top-80" aria-hidden="true">
        <div class="aspect-[1097/845] w-[68.5625rem] bg-gradient-to-tr from-[#10b981] via-[#6366f1] to-[#a855f7] opacity-20" style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)"></div>
    </div>

    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <!-- Hero Header -->
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-3.5 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20 mb-4">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Get In Touch
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-white">
                Let’s build something <br/>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-300 to-indigo-400 bg-clip-text text-transparent">extraordinary together.</span>
            </h1>
            <p class="mt-6 text-lg leading-8 text-slate-300">
                Have questions about Vumbi AI, want to integrate our autonomous multi-agent framework, or explore collaboration? Drop a line below.
            </p>
        </div>

        <!-- Main Content Grid -->
        <div class="mx-auto mt-16 max-w-6xl grid grid-cols-1 gap-12 lg:grid-cols-12 items-start">
            
            <!-- Contact Form Card (7 Cols) -->
            <div class="lg:col-span-7 rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl shadow-2xl">
                <h2 class="text-2xl font-bold text-white mb-2">Send a Message</h2>
                <p class="text-sm text-slate-400 mb-8">Fill out the details below and we will get back to you promptly.</p>

                @if(session('success'))
                    <div class="mb-6 rounded-xl bg-emerald-500/10 border border-emerald-500/30 p-4 text-sm text-emerald-300 flex items-center gap-3">
                        <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                <form action="{{ route('contact.submit') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-2">Your Name</label>
                        <input type="text" name="name" id="name" required placeholder="John Doe" class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition text-sm">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email Address</label>
                        <input type="email" name="email" id="email" required placeholder="john@example.com" class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition text-sm">
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-slate-300 mb-2">Message</label>
                        <textarea name="message" id="message" rows="5" required placeholder="How can we help you?" class="w-full rounded-xl bg-slate-950/80 border border-slate-800 px-4 py-3 text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition text-sm resize-none"></textarea>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/20 hover:from-emerald-400 hover:to-teal-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 transition-all duration-200">
                        Send Message
                    </button>
                </form>
            </div>

            <!-- Direct Contact & Details (5 Cols) -->
            <div class="lg:col-span-5 space-y-6">
                <!-- Info Box -->
                <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-8 backdrop-blur-xl">
                    <h2 class="text-xl font-bold text-white mb-6">Direct Channels</h2>
                    <div class="space-y-6">
                        
                        <!-- Email Link -->
                        <a href="mailto:dante@vumbiventures.com" class="group flex items-start gap-4 p-3 rounded-xl hover:bg-slate-800/50 transition">
                            <div class="rounded-lg bg-emerald-500/10 p-2.5 text-emerald-400 border border-emerald-500/20 group-hover:border-emerald-500/40 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-400">Email Us</div>
                                <div class="text-sm font-semibold text-slate-200 group-hover:text-emerald-400 transition">dante@vumbiventures.com</div>
                            </div>
                        </a>

                        <!-- GitHub Link -->
                        <a href="https://github.com/Dante-VIQ/Autonomous-Agent" target="_blank" class="group flex items-start gap-4 p-3 rounded-xl hover:bg-slate-800/50 transition">
                            <div class="rounded-lg bg-indigo-500/10 p-2.5 text-indigo-400 border border-indigo-500/20 group-hover:border-indigo-500/40 transition">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-400">GitHub Repository</div>
                                <div class="text-sm font-semibold text-slate-200 group-hover:text-indigo-400 transition">Dante-VIQ/Autonomous-Agent</div>
                            </div>
                        </a>

                        <!-- Hackathon Link -->
                        <a href="https://agentsforhumans.devpost.com" target="_blank" class="group flex items-start gap-4 p-3 rounded-xl hover:bg-slate-800/50 transition">
                            <div class="rounded-lg bg-amber-500/10 p-2.5 text-amber-400 border border-amber-500/20 group-hover:border-amber-500/40 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-400">Hackathon Entry</div>
                                <div class="text-sm font-semibold text-slate-200 group-hover:text-amber-400 transition">Agents for Humans 2026</div>
                            </div>
                        </a>

                        <!-- Live Demo Link -->
                        <a href="https://saddlebrown-butterfly-192418.hostingersite.com" target="_blank" class="group flex items-start gap-4 p-3 rounded-xl hover:bg-slate-800/50 transition">
                            <div class="rounded-lg bg-teal-500/10 p-2.5 text-teal-400 border border-teal-500/20 group-hover:border-teal-500/40 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-medium text-slate-400">Live Preview</div>
                                <div class="text-sm font-semibold text-slate-200 group-hover:text-teal-400 transition">vumbi.demo</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Social Profiles Container -->
                <div class="rounded-2xl bg-slate-900/40 border border-slate-800/80 p-6 backdrop-blur-xl">
                    <h3 class="text-xs font-semibold tracking-wider text-slate-400 uppercase mb-4">Follow the Journey</h3>
                    <div class="flex items-center gap-3">
                        <a href="#" class="flex-1 py-2.5 px-3 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white text-xs font-medium text-center transition">
                            🐦 Twitter / X
                        </a>
                        <a href="#" class="flex-1 py-2.5 px-3 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white text-xs font-medium text-center transition">
                            🔗 LinkedIn
                        </a>
                        <a href="#" class="flex-1 py-2.5 px-3 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white text-xs font-medium text-center transition">
                            📺 YouTube
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection