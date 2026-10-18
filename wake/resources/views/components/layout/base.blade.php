@props(['title' => null])
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}Wake</title>
    <link rel="stylesheet" href="{{ app(\Wake\Panel\Panel::class)->asset('panel.css') }}">
    <link rel="preload" href="{{ url('assets/wake/fonts/geist-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    {{ app(\Wake\Panel\Panel::class)->accentStyles() }}
    @livewireStyles
    @stack('head')
</head>
<body>
    {{ $slot }}
    @livewireScripts
    @stack('scripts')
</body>
</html>
