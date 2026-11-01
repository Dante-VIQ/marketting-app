<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysing {{ $url }} — Vumbi AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes pulse-ring {
            0%   { transform: scale(0.85); opacity: 0.9; }
            50%  { transform: scale(1.05); opacity: 0.5; }
            100% { transform: scale(0.85); opacity: 0.9; }
        }
        @keyframes scan-line {
            0%   { top: 0%; opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }
        .pulse-ring  { animation: pulse-ring 2s ease-in-out infinite; }
        .scan-line   { animation: scan-line 2.5s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-white flex items-center justify-center p-4"
      x-data="scanPoller()"
      x-init="startPolling()">

    {{-- Background gradient --}}
    <div class="fixed inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950/20 pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-2xl mx-auto text-center">

        {{-- Animated radar / scanner graphic --}}
        <div class="relative w-40 h-40 mx-auto mb-10">
            {{-- Outer pulse ring --}}
            <div class="pulse-ring absolute inset-0 rounded-full border-2 border-emerald-500/30"></div>
            {{-- Middle ring --}}
            <div class="absolute inset-4 rounded-full border border-emerald-500/20"></div>
            {{-- Inner solid circle --}}
            <div class="absolute inset-8 rounded-full bg-emerald-950/60 border border-emerald-500/40 overflow-hidden flex items-center justify-center">
                {{-- Scan line --}}
                <div class="scan-line absolute left-0 right-0 h-px bg-gradient-to-r from-transparent via-emerald-400 to-transparent"></div>
                {{-- Icon --}}
                <svg class="w-10 h-10 text-emerald-400 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15M14.25 3.104c.251.023.501.05.75.082M19.8 15l-1.5 2.25m0 0a.75.75 0 01-.637.346H6.337a.75.75 0 01-.637-.346L4.2 15m15.6 0H4.2" />
                </svg>
            </div>
            {{-- Corner dots --}}
            <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1 w-2 h-2 rounded-full bg-emerald-400/60"></div>
            <div class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-1 w-2 h-2 rounded-full bg-emerald-400/60"></div>
            <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1 w-2 h-2 rounded-full bg-emerald-400/60"></div>
            <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1 w-2 h-2 rounded-full bg-emerald-400/60"></div>
        </div>

        {{-- Heading --}}
        <h1 class="text-2xl sm:text-3xl font-bold text-white mb-3">
            Analysing your site
        </h1>

        {{-- URL being scanned --}}
        <p class="text-slate-400 text-sm mb-8 truncate max-w-md mx-auto px-4">
            <span class="text-emerald-400">⟳</span>
            {{ $url }}
        </p>

        {{-- Progress steps --}}
        <div class="bg-slate-900/60 border border-slate-700/50 rounded-2xl p-6 mb-8 text-left space-y-3 max-w-md mx-auto backdrop-blur-sm">
            <template x-for="(step, i) in steps" :key="i">
                <div class="flex items-start gap-3">
                    {{-- Status icon --}}
                    <div class="mt-0.5 shrink-0">
                        {{-- Done --}}
                        <template x-if="step.done">
                            <svg class="w-5 h-5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </template>
                        {{-- Active spinner --}}
                        <template x-if="step.active && !step.done">
                            <svg class="w-5 h-5 text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </template>
                        {{-- Waiting --}}
                        <template x-if="!step.active && !step.done">
                            <div class="w-5 h-5 rounded-full border border-slate-600 flex items-center justify-center">
                                <div class="w-1.5 h-1.5 rounded-full bg-slate-600"></div>
                            </div>
                        </template>
                    </div>
                    {{-- Label --}}
                    <div>
                        <p class="text-sm font-medium"
                           :class="step.done ? 'text-emerald-400' : (step.active ? 'text-white' : 'text-slate-500')"
                           x-text="step.label"></p>
                        <p class="text-xs text-slate-600 mt-0.5" x-text="step.sub"></p>
                    </div>
                </div>
            </template>
        </div>

        {{-- Elapsed time --}}
        <p class="text-slate-500 text-xs mb-2">
            Elapsed: <span class="text-slate-400 tabular-nums" x-text="elapsed + 's'"></span>
            &nbsp;·&nbsp; Usually takes 30–60 seconds
        </p>

        {{-- Error state --}}
        <div x-show="errorMsg" x-cloak class="mt-6">
            <div class="bg-rose-950/40 border border-rose-700/50 rounded-xl p-4 max-w-md mx-auto text-sm text-rose-300 mb-4">
                <span x-text="errorMsg"></span>
            </div>
            <a href="{{ route('public.scanner.index') }}"
               class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-semibold text-sm transition-colors">
                ← Try again
            </a>
        </div>

        {{-- Footer note --}}
        <p x-show="!errorMsg" class="text-slate-600 text-xs mt-6">
            We're fetching the page, running AI analysis, and researching your market.<br>
            You can wait here or come back — the report will be ready at this URL.
        </p>

    </div>

    <script>
        function scanPoller() {
            return {
                resultKey:  '{{ $resultKey }}',
                statusUrl:  '{{ route('public.scanner.status', $resultKey) }}',
                elapsed:    0,
                errorMsg:   null,
                pollTimer:  null,
                clockTimer: null,

                steps: [
                    { label: 'Fetching the page',          sub: 'Loading HTML, meta tags and content',      active: true,  done: false },
                    { label: 'AI visibility analysis',     sub: 'Scoring how AI search engines see you',    active: false, done: false },
                    { label: 'Market research',            sub: 'Searching the web for your market data',   active: false, done: false },
                    { label: 'Acquisition strategy',       sub: 'Building your channel recommendations',    active: false, done: false },
                ],

                startPolling() {
                    // Advance steps visually over time to give feedback
                    setTimeout(() => this.advance(0, 1), 4000);
                    setTimeout(() => this.advance(1, 2), 18000);
                    setTimeout(() => this.advance(2, 3), 38000);

                    // Clock
                    this.clockTimer = setInterval(() => this.elapsed++, 1000);

                    // Poll status every 3 seconds
                    this.pollTimer = setInterval(() => this.poll(), 3000);
                    // First poll after 5s (job needs time to start)
                    setTimeout(() => this.poll(), 5000);
                },

                advance(doneIdx, activeIdx) {
                    if (this.steps[doneIdx]) this.steps[doneIdx].done = true;
                    if (this.steps[activeIdx]) this.steps[activeIdx].active = true;
                },

                async poll() {
                    try {
                        const res  = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
                        const data = await res.json();

                        if (data.status === 'done') {
                            clearInterval(this.pollTimer);
                            clearInterval(this.clockTimer);
                            // Mark all steps done for a moment then redirect
                            this.steps.forEach(s => { s.done = true; s.active = false; });
                            setTimeout(() => window.location = data.redirect_url, 600);

                        } else if (data.status === 'error') {
                            clearInterval(this.pollTimer);
                            clearInterval(this.clockTimer);
                            this.errorMsg = 'The scan failed. Please try again.';

                        } else if (data.status === 'expired') {
                            clearInterval(this.pollTimer);
                            clearInterval(this.clockTimer);
                            this.errorMsg = 'The session expired. Please submit a new URL.';
                        }
                        // 'pending' → keep polling
                    } catch (err) {
                        // Network error — keep trying silently for up to 2 minutes
                        if (this.elapsed > 120) {
                            clearInterval(this.pollTimer);
                            clearInterval(this.clockTimer);
                            this.errorMsg = 'Lost connection while waiting. Please try again.';
                        }
                    }
                }
            };
        }
    </script>
</body>
</html>
