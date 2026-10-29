<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vumbi AI – Pending Approval</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-950 text-slate-100 min-h-full flex items-center justify-center px-4 py-12">

    {{-- Ambient blurs --}}
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-24 -left-20 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-indigo-500/8 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-lg">

        @if($user->approval_status === 'rejected')

            {{-- Rejected state --}}
            <div class="bg-slate-900/80 backdrop-blur-xl border border-red-900/40 rounded-2xl shadow-2xl p-8 text-center">
                <div class="w-14 h-14 rounded-full bg-red-500/10 border border-red-500/20 flex items-center justify-center mx-auto mb-5">
                    <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-white">Request not approved</h1>
                <p class="mt-3 text-sm text-slate-400 leading-relaxed">
                    Your account request was not approved by the platform administrator.<br>
                    If you believe this is a mistake, please reach out directly.
                </p>
                <p class="mt-3 text-xs text-slate-600">{{ $user->email }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button class="px-5 py-2.5 text-sm font-medium bg-slate-800 text-slate-300 rounded-xl border border-slate-700 hover:bg-slate-700 hover:text-white transition-colors">
                        Sign out
                    </button>
                </form>
            </div>

        @else

            {{-- Pending state --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-x-1.5 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-500/20 mb-4">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    Waiting for approval
                </div>
                <h1 class="text-3xl font-extrabold text-white tracking-tight">You're on the list, {{ explode(' ', $user->name)[0] }}</h1>
                <p class="mt-2 text-sm text-slate-400">The platform administrator will review your account shortly.</p>
            </div>

            <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/60 rounded-2xl shadow-2xl overflow-hidden">

                {{-- Status bar --}}
                <div class="bg-amber-500/5 border-b border-amber-500/10 px-6 py-4 flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></div>
                    <p class="text-sm text-amber-300/80">
                        Account under review · Signed in as <span class="text-amber-400 font-medium">{{ $user->email }}</span>
                    </p>
                </div>

                {{-- What you'll get --}}
                <div class="p-6">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-4">What's waiting for you</p>
                    <div class="space-y-3">
                        @foreach([
                            ['icon' => '🤖', 'title' => 'Daily AI Brief', 'desc' => 'Strategic marketing diagnosis every morning at 6 AM'],
                            ['icon' => '📣', 'title' => 'Content Autopilot', 'desc' => 'AI drafts posts, you approve before anything goes live'],
                            ['icon' => '📊', 'title' => 'Impact Tracking', 'desc' => 'See exactly how social content moves your site traffic'],
                            ['icon' => '🔍', 'title' => 'Competitor Intelligence', 'desc' => 'Automatic discovery and monitoring of competitor brands'],
                            ['icon' => '🌍', 'title' => 'AI Visibility', 'desc' => 'Know if AI assistants recommend your brand'],
                        ] as $feature)
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-800/40 border border-slate-700/30">
                                <span class="text-lg shrink-0 mt-0.5">{{ $feature['icon'] }}</span>
                                <div>
                                    <p class="text-sm font-medium text-white">{{ $feature['title'] }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5">{{ $feature['desc'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Footer --}}
                <div class="border-t border-slate-800/60 px-6 py-4 flex items-center justify-between">
                    <p class="text-xs text-slate-600">No need to register again. We'll let you in when it's ready.</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-xs text-slate-500 hover:text-slate-300 transition-colors">Sign out</button>
                    </form>
                </div>

            </div>

        @endif

    </div>
</body>
</html>
