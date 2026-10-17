@props(['route', 'match' => null, 'icon', 'badge' => null, 'badgeColor' => 'emerald'])

@php
    $active = request()->routeIs($match ?? $route);
    $badgeClasses = [
        'emerald' => 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border-emerald-200/50 dark:border-emerald-800/50',
        'sky'     => 'bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 border-sky-200/50 dark:border-sky-800/50',
        'amber'   => 'bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border-amber-200/50 dark:border-amber-800/50',
        'slate'   => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200/50 dark:border-slate-700/50',
    ][$badgeColor] ?? '';
@endphp

<a href="{{ route($route) }}"
    class="group flex items-center px-3 py-2 text-sm font-medium rounded-xl transition-all duration-150 {{ $active ? 'bg-white/90 dark:bg-slate-800/90 text-emerald-600 dark:text-emerald-400 shadow-xs border border-emerald-100 dark:border-emerald-900/50' : 'text-slate-600 dark:text-slate-300 hover:bg-white/50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white' }}">
    <svg class="w-5 h-5 mr-3 flex-shrink-0 {{ $active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}"
        fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}" />
    </svg>
    <span>{{ $slot }}</span>
    @if ($badge)
        <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $badgeClasses }}">{{ $badge }}</span>
    @endif
</a>
