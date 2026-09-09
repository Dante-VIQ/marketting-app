{{-- resources/views/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vumbi AI - @yield('title', 'Welcome')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="antialiased bg-slate-950 text-slate-100 selection:bg-emerald-500 selection:text-white transition-colors duration-200 h-full flex flex-col">
    <!-- Ambient Backdrop Accent Blurs -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-24 -left-20 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-20 w-96 h-96 bg-sky-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-indigo-500/5 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col flex-grow">
        <!-- Glass Navigation Bar (Always Dark) -->
        <nav class="sticky top-0 z-40 bg-slate-950/70 backdrop-blur-xl border-b border-slate-800/60 shadow-2xs transition-colors duration-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Brand + Primary Navigation Links -->
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-emerald-400 tracking-tight">Vumbi</span>
                            <span class="text-2xl font-bold text-white">AI</span>
                        </a>
                        <span class="ml-3 px-2.5 py-0.5 text-xs font-semibold bg-emerald-950/40 text-emerald-300 border border-emerald-800/50 rounded-full shadow-2xs">
                            Ecosystem
                        </span>

                        <!-- Public Navigation Links -->
                        <div class="hidden sm:flex space-x-1 sm:ms-10">
                            <a href="{{ url('/') }}" 
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->routeIs('/') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                {{ __('Home') }}
                            </a>

                            <a href="{{ url('about') }}" 
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->routeIs('about') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                {{ __('About') }}
                            </a>

                            <a href="{{ url('contact') }}" 
                               class="px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150 {{ request()->routeIs('contact') ? 'text-emerald-400 bg-emerald-950/50' : 'text-slate-300 hover:text-white hover:bg-slate-900/60' }}">
                                {{ __('Contact') }}
                            </a>
                        </div>
                    </div>

                    <!-- Right Controls (Authentication Buttons – Always Dark) -->
                    <div class="flex items-center space-x-2">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-500 text-white shadow-xs hover:bg-emerald-400 transition-all duration-200 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                    </svg>
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="px-3 py-2 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-900/60 transition-colors">
                                    {{ __('Sign In') }}
                                </a>

                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900/80 backdrop-blur-md text-white shadow-2xs border border-slate-800 hover:bg-slate-800/80 hover:text-emerald-400 transition-all duration-200">
                                        {{ __('Create Account') }}
                                    </a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Public Content Area -->
        <main class="flex-grow">
            @yield('content')
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>

</html>