{{-- Light / dark switch. Follows the device until the user picks one; the choice is remembered. --}}
<button type="button"
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        apply(dark, save) {
            this.dark = dark;
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            if (save) { try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {} }
        },
        init() {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            mq.addEventListener('change', e => {
                var saved = null;
                try { saved = localStorage.getItem('theme'); } catch (err) {}
                if (!saved) this.apply(e.matches, false);
            });
        }
    }"
    @click="apply(!dark, true)"
    :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
    :title="dark ? 'Switch to light mode' : 'Switch to dark mode'"
    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-emerald-600 dark:hover:text-emerald-400 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 transition-colors">
    <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
    <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
    </svg>
</button>
