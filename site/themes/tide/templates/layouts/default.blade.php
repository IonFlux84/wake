<!doctype html>
<html lang="{{ $entry?->language() ?? $site->language() }}">
<head>
@wakeHead
    <link rel="stylesheet" href="{{ theme_asset('tide.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="site-title" href="{{ $site->url() }}">{{ $site->title() }}</a>
        <nav class="site-nav" aria-label="{{ __('Main menu') }}">
            @foreach ($site->menu() as $item)
                <a href="{{ $item->url() }}" @if ($entry && $entry->id() === $item->id()) aria-current="page" @endif>{{ $item->title() }}</a>
            @endforeach
        </nav>
        @include('theme::snippets.languages')
    </header>

    @if ($entry?->isFallback())
        <p class="notice">{{ __('This page is not available in your language yet.') }}</p>
    @endif

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <span>© {{ date('Y') }} {{ $site->title() }}</span>
        <span class="footer-links">@wakeConsentLink · <a href="{{ app(\Wake\Localization\Languages::class)->url('feed.xml') }}">RSS</a></span>
    </footer>
</body>
</html>
