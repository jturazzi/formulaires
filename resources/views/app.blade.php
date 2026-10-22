<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Formulaires') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @routes
        <script>window.__SENTRY__ = {{ \Illuminate\Support\Js::from(['dsn' => config('sentry.js_dsn'), 'environment' => config('sentry.environment') ?? app()->environment(), 'release' => config('sentry.release'), 'tracesSampleRate' => config('sentry.traces_sample_rate')]) }};</script>
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
