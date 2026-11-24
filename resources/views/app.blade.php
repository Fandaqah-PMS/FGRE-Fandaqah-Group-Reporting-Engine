<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme-pref="{{ auth()->user()?->theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" type="image/png" href="/brand/mark.png">
        {{-- Theme before first paint (no flash): saved choice, else this browser's, else the OS. --}}
        <script>
            (function () {
                var el = document.documentElement, pref = el.dataset.themePref;
                try { pref = pref || localStorage.getItem('fgre.theme'); } catch (e) {}
                var dark = pref === 'dark' || ((!pref || pref === 'system') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                el.dataset.theme = dark ? 'dark' : 'light';
                el.style.colorScheme = dark ? 'dark' : 'light';
            })();
        </script>
        <title inertia>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-paper text-ink antialiased">
        @inertia
    </body>
</html>
