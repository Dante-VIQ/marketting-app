{{-- resources/views/layouts/studio.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vumbi AI Studio - @yield('title', 'Studio')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="antialiased bg-slate-950 text-slate-100 selection:bg-violet-500 selection:text-white h-full">
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-24 -left-20 w-96 h-96 bg-violet-500/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-20 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col min-h-screen">
        <nav class="sticky top-0 z-40 bg-slate-950/70 backdrop-blur-xl border-b border-slate-800/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2">
                        <span class="text-2xl font-bold text-violet-400 tracking-tight">Vumbi</span>
                        <span class="text-2xl font-bold text-white">Studio</span>
                    </a>
                    <div class="flex items-center space-x-4 text-sm">
                        <a href="{{ route('dashboard') }}" class="text-slate-300 hover:text-white transition">← Back to Console</a>
                        @auth
                            <span class="text-slate-500">{{ auth()->user()->name }}</span>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-1">
            @yield('content')
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>