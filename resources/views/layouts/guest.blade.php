{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Revora — @yield('title', 'AI Marketing Automation')</title>
    <meta name="description" content="@yield('meta_description', 'Revora is an autonomous AI marketing platform that monitors your business, surfaces opportunities, and executes tasks — only bringing you in when human judgment is needed.')">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="antialiased bg-slate-950 text-slate-100 selection:bg-emerald-500 selection:text-white transition-colors duration-200 h-full flex flex-col">
    <!-- Ambient Backdrop -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-24 -left-20 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-20 w-96 h-96 bg-sky-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-indigo-500/5 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col flex-grow">
        <!-- Navigation -->
        <nav class="sticky top-0 z-40 bg-slate-950/70 backdrop-blur-xl border-b border-slate-800/60 shadow-2xs transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Brand -->
                    <div class="flex items-center">
                        <a href="{{ route('landing') }}" class="flex items-center space-x-1">
                            <span class="text-2xl font-bold text-emerald-400 tracking-tight">Re</span><span class="text-2xl font-bold text-white tracking-tight">vora</span>
                        </a>
                        <span class="ml-3 px-2.5 py-0.5 text-xs font-semibold bg-emerald-950/40 text-emerald-300 border border-emerald-800/50 rounded-full">
                            AI
                        </span>

                        <!-- Nav Links -->
                        <div class="hidden sm:flex space-x-1 sm:ms-10">
                            <a href="{{ url('/') }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->is('/') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                Home
                            </a>
                            <a href="{{ route('public.scanner.index') }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->routeIs('public.scanner.*') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                Free Scan
                            </a>
                            <a href="{{ url('about') }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->is('about') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                About
                            </a>
                            <a href="{{ url('contact') }}"
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->is('contact') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                Contact
                            </a>
                        </div>
                    </div>

                    <!-- Auth Controls -->
                    <div class="flex items-center space-x-2">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-500 text-white shadow-xs hover:bg-emerald-400 transition-all duration-200 flex items-center gap-1.5">
                                    Dashboard →
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-900/60 transition-colors">
                                    Sign In
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition-all duration-200">
                                        Get started free
                                    </a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="flex-grow">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800/60 bg-slate-950/80 py-8">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-emerald-400">Re</span><span class="font-bold text-slate-300">vora</span>
                    <span class="text-slate-700">·</span>
                    <span>Autonomous AI marketing for lean teams</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="hover:text-slate-300 transition-colors">Home</a>
                    <a href="{{ route('public.scanner.index') }}" class="hover:text-slate-300 transition-colors">Free Scan</a>
                    <a href="{{ url('about') }}" class="hover:text-slate-300 transition-colors">About</a>
                    <a href="{{ url('contact') }}" class="hover:text-slate-300 transition-colors">Contact</a>
                </div>
                <span>© {{ date('Y') }} Revora. Built for builders.</span>
            </div>
        </footer>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
