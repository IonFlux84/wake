@props(['title' => null, 'active' => null])
@php
    $account = auth()->user();
    $site = app(\Wake\Content\Site::class);
    $nav = [
        [null, [
            ['dashboard', 'wake.dashboard', __('Dashboard'), 'dashboard'],
            ['posts', 'wake.posts', __('Posts'), 'posts'],
            ['pages', 'wake.pages', __('Pages'), 'pages'],
            ['media', 'wake.media.index', __('Media'), 'media'],
        ]],
        [__('Insights'), [
            ['stats', 'wake.stats', __('Statistics'), 'stats'],
            ['consent', 'wake.consent', __('Consent'), 'consent'],
        ]],
        [__('Manage'), [
            ['assistant', 'wake.assistants', __('Assistants'), 'assistant'],
            ['team', 'wake.team', __('Team'), 'team'],
            ['settings', 'wake.settings', __('Settings'), 'settings'],
        ]],
    ];
@endphp
<x-wake::layout.base :title="$title">
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('wake.dashboard') }}">
            <span class="brand-mark"><x-wake::logo /></span>
            <span style="min-width:0">
                <span class="brand-name" style="display:block">{{ $site->title() }}</span>
                <span class="brand-url">{{ parse_url(url('/'), PHP_URL_HOST) }} ↗</span>
            </span>
        </a>

        <nav class="nav" aria-label="{{ __('Main menu') }}">
            @foreach ($nav as [$label, $items])
                @if ($label)<div class="nav-label">{{ $label }}</div>@endif
                @foreach ($items as [$key, $route, $text, $icon])
                    @if (Route::has($route))
                        <a href="{{ route($route) }}" @class(['on' => $active === $key]) @if ($active === $key) aria-current="page" @endif>
                            <x-wake::icon :name="$icon" />{{ $text }}
                        </a>
                    @endif
                @endforeach
            @endforeach
        </nav>

        <div class="me">
            <span class="avatar">{{ $account->initials() }}</span>
            <span class="me-text"><b>{{ $account->name() }}</b><span>{{ $account->role()->label() }}</span></span>
            <form method="post" action="{{ route('wake.logout') }}">
                @csrf
                <button class="btn btn-small" type="submit" title="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}"><x-wake::icon name="logout" /></button>
            </form>
        </div>
    </aside>

    <main class="main">
        <div class="main-inner">
            @if (session('status'))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</x-wake::layout.base>
