<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="theme-color" content="#09090b">
        <title>{{ config('home-dashboard.title') }}</title>

        @vite(config('home-dashboard.vite'))

        <style>
            /* Scale every rem-based Tailwind size with the screen so the shell fills any display. */
            html { font-size: clamp(14px, calc(4px + 0.5vw + 0.9vh), 26px); }
            html, body { height: 100%; }
            body { overscroll-behavior: none; -webkit-tap-highlight-color: transparent; }
            [x-cloak] { display: none !important; }
        </style>
    </head>
    <body class="h-dvh w-dvw overflow-hidden select-none bg-zinc-950 text-zinc-100 antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
