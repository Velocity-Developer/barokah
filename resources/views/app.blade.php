<!DOCTYPE html>
@php
    // Only the dashboard follows the appearance setting; the storefront and
    // the auth screens are always light (see App\Support\PageChrome).
    $themedPage = \App\Support\PageChrome::usesDashboard($page['component'] ?? null);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $themedPage && ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Dashboard only: follow the system preference before Vue boots. --}}
        @if ($themedPage)
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
        @endif

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @php
            // Favicon uploaded in Admin → Settings → Branding; the bundled icons are only a fallback.
            $brandFavicon = rescue(
                fn () => app(\App\Services\SettingsService::class)->allPublic()['branding.favicon_url'] ?? null,
                null,
                false,
            );
        @endphp
        @if (is_string($brandFavicon) && $brandFavicon !== '')
            <link rel="icon" href="{{ $brandFavicon }}">
            <link rel="apple-touch-icon" href="{{ $brandFavicon }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
