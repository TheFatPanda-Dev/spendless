<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f6f7f2;
            }

            html.dark {
                background-color: #111714;
            }
        </style>

        <title inertia>{{ config('app.name', 'SpendLess') }}</title>

        <link rel="icon" href="/images/brand/favicon.ico" sizes="any">
        <link rel="icon" href="/images/brand/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/images/brand/app-icon-180.png">
        <link rel="manifest" href="/images/brand/site.webmanifest">
        <meta name="theme-color" content="#f6f7f2" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#111714" media="(prefers-color-scheme: dark)">
        <link rel="preload" href="/fonts/roboto/Roboto-Regular.ttf" as="font" type="font/ttf" crossorigin>

        @if (request()->routeIs('home'))
            <meta name="description" content="SpendLess brings your banks, investments and savings in the EU and the UK into one place, in euros and pounds. See what went out. Know what's left.">
            <meta property="og:type" content="website">
            <meta property="og:title" content="SpendLess — Money made simple">
            <meta property="og:description" content="See what went out. Know what's left.">
            <meta property="og:url" content="{{ url('/') }}">
            <meta property="og:image" content="{{ url('/images/brand/link-preview.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta name="twitter:card" content="summary_large_image">
        @endif

        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
