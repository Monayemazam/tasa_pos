<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" style="color-scheme: light;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Self-hosted fonts: Hanken Grotesk & Noto Sans Bengali --}}
        <link rel="stylesheet" href="/fonts/fonts.css">

        {{-- Inline style to prevent FOUC with exact light page background --}}
        <style>
            :root {
                color-scheme: light;
            }
            html, body {
                background-color: #F4F6F6;
                color: #2B3A38;
            }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Tasa POS') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased bg-page text-ink-700 min-h-screen">
        <x-inertia::app />
    </body>
</html>
