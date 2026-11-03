<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysing {{ $url }} — Revora</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes progress-bar {
            0%   { width: 4%; }
            10%  { width: 20%; }
            30%  { width: 42%; }
            55%  { width: 65%; }
            75%  { width: 80%; }
            90%  { width: 90%; }
            100% { width: 94%; }
        }
        .progress-auto { animation: progress-bar 60s ease-out forwards; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-white"
      x-data="scanPoller()"
      x-init="startPolling()">

    {{-- Ambient --}}
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute -top-32 -left-24 w-[500px] h-[500px] bg-emerald-500/8 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 -right-24 w-[400px] h-[400px] bg-indigo-500/8 rounded-full blur-3xl"></div>
    </div>

    {{-- Nav --}}
    <nav class="relative z-10 px-6 py-4 border-b border-slate-800/60 bg-slate-950/70 backdrop-blur-xl">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-1">
                <span class="text-xl font-bold text-emerald-400">Re</span><span class="text-xl font-bold text-white">vora</span>
            </a>
            <span class="text-xs text-slate-500 truncate max-w-xs hidden sm:block">{{ $url }}</span>
        </div>
    </nav>

    <div class="relative z-10 max-w-2xl mx-auto px-4 py-12 sm:py-20">

        {{-- Header --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-950/60 border border-emerald-800/50 text-emerald-300 text-xs font-semibold mb-5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Running AI analysis
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-3">Scanning your website</h1>
            <p class="text-slate-400 text-sm truncate max-w-sm mx-auto">{{ $url }}</p>
        </div>

        {{-- Progress bar --}}
        <div class="mb-8">
            <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
                <span x-text="currentStepLabel"></span>
                <span x-text="Math.round(pct) + '%'"></span>
            </div>
            <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-700"
                     :style="'width:' + pct + '%'"></div>
            </div>
            <p class="text-xs text-slate-600 mt-2 text-right" x-show="timeLeft > 0">
                about <span x-text="timeLeft"></span> s left
            </p>
        </div>

        {{-- Steps list (mirrors visibly-ai UX) --}}
        <div class="bg-slate-900/60 border border-slate-700/50 rounded-2xl divide-y divide-slate-800/60 backdrop-blur-sm mb-8">
            <template x-for="(step, i) in steps" :key="i">
                <div class="flex items-start gap-4 px-5 py-4">
                    {{-- Icon --}}
                    <div class="mt-0.5 shrink-0 w-6 flex justify-center">
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
                            <div class="w-5 h-5 rounded-full border border-slate-700 flex items-center justify-center">
                                <div class="w-1.5 h-1.5 rounded-full bg-slate-700"></div>
                            </div>
                        </template>
                    </div>

                    {{-- Text --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold leading-snug"
                           :class="step.done ? 'text-emerald-400' : (step.active ? 'text-white' : 'text-slate-500')"
                           x-text="step.label"></p>
                        <p class="text-xs mt-0.5 leading-relaxed"
                           :class="step.active && !step.done ? 'text-slate-400' : 'text-slate-600'"
                           x-text="step.sub"></p>
                    </div>

                    {{-- Badge for active step --}}
                    <div x-show="step.active && !step.done" class="shrink-0">
                        <span class="text-xs text-emerald-400 bg-emerald-950/60 border border-emerald-800/50 px-2 py-0.5 rounded-full">running</span>
                    </div>
                    <div x-show="step.done" class="shrink-0">
                        <span class="text-xs text-slate-500">done</span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Background notice --}}
        <p class="text-center text-slate-600 text-xs mb-8" x-show="!errorMsg">
            Runs in the background — feel free to leave this tab open.
        </p>

        {{-- Error --}}
        <div x-show="errorMsg" x-cloak class="mt-4">
            <div class="bg-rose-950/40 border border-rose-700/50 rounded-xl p-4 text-sm text-rose-300 mb-4" x-text="errorMsg"></div>
            <a href="{{ route('public.scanner.index') }}"
               class="flex items-center justify-center gap-2 w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-semibold text-sm transition-colors">
                ← Try another URL
            </a>
        </div>

    </div>

    <script>
    function scanPoller() {
        return {
            resultKey:    '{{ $resultKey }}',
            statusUrl:    '{{ route('public.scanner.status', $resultKey) }}',
            elapsed:      0,
            timeLeft:     60,
            pct:          2,
            errorMsg:     null,
            pollTimer:    null,
            clockTimer:   null,
            currentStepLabel: 'Starting…',

            steps: [
                {
                    label:  'Reading your website',
                    sub:    'We fetch your homepage and extract content, meta tags, and headings.',
                    active: true,
                    done:   false,
                },
                {
                    label:  'AI visibility analysis',
                    sub:    'Scoring how AI search engines like ChatGPT and Gemini see your business.',
                    active: false,
                    done:   false,
                },
                {
                    label:  'Market research',
                    sub:    'Live web search for competitors, market data, and audience signals.',
                    active: false,
                    done:   false,
                },
                {
                    label:  'Acquisition intelligence',
                    sub:    'Building your channel recommendations and content gap report.',
                    active: false,
                    done:   false,
                },
                {
                    label:  'Generating your report',
                    sub:    'Assembling the final AI visibility score and action plan.',
                    active: false,
                    done:   false,
                },
            ],

            startPolling() {
                // Visual step advancement — timed to match typical job duration
                const advances = [
                    [5000,  0, 1, 22],
                    [18000, 1, 2, 48],
                    [35000, 2, 3, 70],
                    [50000, 3, 4, 85],
                ];
                advances.forEach(([ms, doneIdx, activeIdx, pctVal]) => {
                    setTimeout(() => {
                        if (this.steps[doneIdx]) this.steps[doneIdx].done = true;
                        if (this.steps[activeIdx]) { this.steps[activeIdx].active = true; this.pct = pctVal; this.currentStepLabel = this.steps[activeIdx].label; }
                    }, ms);
                });

                // Clock countdown
                this.clockTimer = setInterval(() => {
                    this.elapsed++;
                    this.timeLeft = Math.max(0, 60 - this.elapsed);
                }, 1000);

                // Poll every 3 s, first after 6 s
                setTimeout(() => this.poll(), 6000);
                this.pollTimer = setInterval(() => this.poll(), 3000);
            },

            async poll() {
                try {
                    const res  = await fetch(this.statusUrl, { headers: { Accept: 'application/json' } });
                    const data = await res.json();

                    if (data.status === 'done') {
                        clearInterval(this.pollTimer);
                        clearInterval(this.clockTimer);
                        this.pct = 100;
                        this.steps.forEach(s => { s.done = true; s.active = false; });
                        this.currentStepLabel = 'Complete!';
                        setTimeout(() => { window.location = data.redirect_url; }, 500);

                    } else if (data.status === 'error') {
                        clearInterval(this.pollTimer);
                        clearInterval(this.clockTimer);
                        this.errorMsg = 'The scan encountered an error. Please try again.';

                    } else if (data.status === 'expired') {
                        clearInterval(this.pollTimer);
                        clearInterval(this.clockTimer);
                        this.errorMsg = 'The session expired. Please submit a new URL.';
                    }
                    // 'pending' → keep polling
                } catch (err) {
                    if (this.elapsed > 150) {
                        clearInterval(this.pollTimer);
                        clearInterval(this.clockTimer);
                        this.errorMsg = 'Connection lost while waiting. Please try again.';
                    }
                }
            }
        };
    }
    </script>
</body>
</html>
