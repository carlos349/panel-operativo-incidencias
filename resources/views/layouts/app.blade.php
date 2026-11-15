<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-theme="{{ config('themes.default') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- Aplica el tema guardado antes de pintar, para que no parpadee el tema por defecto. --}}
    <script>
        (function () {
            var themes = @json(array_keys(config('themes.available')));
            var saved = null;
            try { saved = localStorage.getItem('theme'); } catch (e) {}
            if (themes.indexOf(saved) !== -1) {
                document.documentElement.dataset.theme = saved;
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-canvas font-sans text-ink antialiased">
    {{ $slot }}

    <x-ui.toasts />

    @livewireScripts
</body>
</html>
