@props(['title' => null, 'active' => null])
@php
    $account = auth()->user();
    $site = app(\Wake\Content\Site::class);
    // [key, route, label, icon, capability needed (null = everyone)]
    $nav = [
        [null, [
            ['dashboard', 'wake.dashboard', __('Dashboard'), 'dashboard', null],
            ['posts', 'wake.posts', __('Posts'), 'posts', 'content.write'],
            ['pages', 'wake.pages', __('Pages'), 'pages', 'content.write'],
            ['categories', 'wake.categories', __('Categories'), 'tag', 'categories.manage'],
            ['media', 'wake.media.index', __('Media'), 'media', 'content.write'],
        ]],
        [__('Insights'), [
            ['stats', 'wake.stats', __('Statistics'), 'stats', 'stats.view'],
            ['consent', 'wake.consent', __('Consent'), 'consent', 'consent.manage'],
        ]],
        [__('Manage'), [
            ['themes', 'wake.themes', __('Themes'), 'brush', 'themes.manage'],
            ['assistant', 'wake.assistants', __('Assistants'), 'assistant', 'assistants.use'],
            ['team', 'wake.team', __('Team'), 'team', 'team.manage'],
            ['settings', 'wake.settings', __('Settings'), 'settings', null],
        ]],
    ];
    $visible = fn ($item) => Route::has($item[1]) && ($item[4] === null || $account->can($item[4]));
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
                @if ($label && collect($items)->contains($visible))<div class="nav-label">{{ $label }}</div>@endif
                @foreach ($items as $item)
                    @php([$key, $route, $text, $icon] = $item)
                    @if ($visible($item))
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
            @if (config('wake.closed'))
                <div class="flash" role="status" style="background:var(--accent);color:var(--on-accent)">{{ __('The site is closed: only signed-in team members can see it.') }} @if ($account->can('settings.manage'))<a href="{{ route('wake.settings') }}" style="color:inherit;font-weight:700">{{ __('Open it') }} →</a>@endif</div>
            @endif
            @if (session('status'))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</x-wake::layout.base>
