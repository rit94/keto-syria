{{-- Shared <head> for layouts::app and layouts::guest. Expects $title (string|null). --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#faf8f4" data-theme-color>

<title>{{ filled($title ?? null) ? $title.' · '.__('app.name') : __('app.name') }}</title>

{{-- Apply the stored theme before first paint. window.KetoTheme: get() / set('light'|'dark'|'system') / toggle(). --}}
<script>
    (function () {
        var root = document.documentElement;
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function stored() {
            try { return localStorage.getItem('theme'); } catch (e) { return null; }
        }

        function apply() {
            var theme = stored();
            var dark = theme === 'dark' || (theme !== 'light' && media.matches);
            root.classList.toggle('dark', dark);
            var meta = document.querySelector('meta[data-theme-color]');
            if (meta) meta.setAttribute('content', dark ? '#151411' : '#faf8f4');
        }

        window.KetoTheme = {
            get: function () { return stored() || 'system'; },
            set: function (theme) {
                try {
                    if (theme === 'system') localStorage.removeItem('theme');
                    else localStorage.setItem('theme', theme);
                } catch (e) {}
                apply();
            },
            toggle: function () { this.set(root.classList.contains('dark') ? 'light' : 'dark'); },
        };

        apply();
        if (media.addEventListener) media.addEventListener('change', apply);
        document.addEventListener('livewire:navigated', apply);
    })();
</script>

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
