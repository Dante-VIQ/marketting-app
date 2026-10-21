{{-- Applies the saved (or device) colour theme before first paint to avoid a flash. --}}
<script>
    (function () {
        var dark = false;
        try {
            var saved = localStorage.getItem('theme');
            dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        } catch (e) {}
        var root = document.documentElement;
        root.classList.toggle('dark', dark);
        root.style.colorScheme = dark ? 'dark' : 'light';
    })();
</script>
