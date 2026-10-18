@php
    $languages = app(\Wake\Localization\Languages::class);
    $accent = theme_option('accent', '#C6FF3D');
@endphp
<!doctype html>
<html lang="{{ $entry?->language() ?? $site->language() }}">
<head>
@wakeHead
    <link rel="preload" href="{{ theme_asset('fonts/archivo-latin-wdth-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ theme_asset('tide.css') }}">
    <style>:root{--accent:{{ preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#C6FF3D' }}}</style>
    <style>@font-face{font-family:Archivo;src:url({{ theme_asset('fonts/archivo-latin-wdth-normal.woff2') }}) format("woff2");font-weight:100 900;font-stretch:62% 125%;font-display:swap}</style>
</head>
<body>
    <a class="skip" href="#main">{{ __('Skip to content') }}</a>
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="site-title" href="{{ $site->url() }}">{{ $site->title() }}</a>
            <button class="menu-toggle" type="button" aria-label="{{ __('Menu') }}" aria-expanded="false" aria-controls="site-nav"
                onclick="const open = document.body.classList.toggle('menu-open'); this.setAttribute('aria-expanded', open)"><span></span><span></span></button>
                <nav class="site-nav" id="site-nav" aria-label="{{ __('Main menu') }}">
                    @foreach ($site->menu() as $item)
                        <a href="{{ $item->url() }}" @if ($entry && ($entry->id() === $item->id() || str_starts_with($entry->id(), $item->id().'/'))) aria-current="page" @endif>{{ $item->title() }}</a>
                    @endforeach
                    <a class="search-link" href="{{ $languages->url('search') }}" aria-label="{{ __('Search') }}">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                        <span class="only-mobile">{{ __('Search') }}</span>
                    </a>
                    @include('theme::snippets.languages')
                </nav>
        </div>
    </header>

    @if ($entry?->isFallback())
        <p class="wrap notice">{{ __('This page is not available in your language yet.') }}</p>
    @endif

    <main id="main" class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap footer-inner">
            <div>
                <a class="site-title" href="{{ $site->url() }}">{{ $site->title() }}</a>
                @if ($site->description())<p class="muted">{{ $site->description() }}</p>@endif
            </div>
            <nav class="footer-nav" aria-label="{{ __('Footer') }}">
                @foreach ($site->menu() as $item)<a href="{{ $item->url() }}">{{ $item->title() }}</a>@endforeach
                <a href="{{ $languages->url('feed.xml') }}">RSS</a>
                @wakeConsentLink
            </nav>
        </div>
        @if (theme_option('footer_text'))<p class="wrap made">{{ theme_option('footer_text') }}</p>@endif
        <p class="wrap made">© {{ date('Y') }} {{ $site->title() }} · {{ __('Made with') }} <a href="https://getwake.dev">Wake</a></p>
    </footer>
</body>
</html>
