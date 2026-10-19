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
            ['menus', 'wake.menus', __('Menus'), 'menu', 'structure.manage'],
            ['categories', 'wake.categories', __('Categories'), 'tag', 'categories.manage'],
            ['media', 'wake.media.index', __('Media'), 'media', 'content.write'],
        ]],
        [__('Insights'), [
            ['stats', 'wake.stats', __('Statistics'), 'stats', 'stats.view'],
            ['consent', 'wake.consent', __('Consent'), 'consent', 'consent.manage'],
        ]],
        [__('Manage'), [
            ['themes', 'wake.themes', __('Themes'), 'brush', 'themes.manage'],
            ['plugins', 'wake.plugins', __('Plugins'), 'plug', 'settings.manage'],
            ['assistant', 'wake.assistants', __('Assistants'), 'assistant', 'assistants.use'],
            ['team', 'wake.team', __('Team'), 'team', 'team.manage'],
            ['backups', 'wake.backups', __('Backups'), 'download', 'settings.manage'],
            ['redirects', 'wake.redirects', __('Redirects'), 'arrow-left', 'settings.manage'],
            ...app(\Wake\Plugins\Plugins::class)->menu(),
            ['settings', 'wake.settings', __('Settings'), 'settings', null],
        ]],
    ];
    $visible = fn ($item) => Route::has($item[1]) && ($item[4] === null || $account->can($item[4]));
@endphp
<x-wake::layout.base :title="$title">
<a class="skip" href="#main">{{ __('Skip to content') }}</a>
<div class="shell">
    <aside class="sidebar" x-data="{ open: false }" :class="{ 'is-open': open }" @keydown.escape="if (open) { open = false; $nextTick(() => $refs.toggle.focus()) }">
        <div class="sidebar-top">
            <a class="brand" href="{{ route('wake.dashboard') }}">
                <x-wake::logo :size="28" :tile="true" id="nav" />
                <span class="brand-text">
                    <span class="brand-name">{{ $site->title() }}</span>
                    <span class="brand-url">{{ parse_url(url('/'), PHP_URL_HOST) }} ↗</span>
                </span>
            </a>
            <button type="button" class="btn btn-small btn-icon menu-toggle" x-ref="toggle" @click="open = !open" :aria-expanded="open.toString()" aria-controls="panel-nav" aria-label="{{ __('Open menu') }}" title="{{ __('Open menu') }}" :aria-label="open ? @js(__('Close menu')) : @js(__('Open menu'))" :title="open ? @js(__('Close menu')) : @js(__('Open menu'))"><x-wake::icon name="menu" /></button>
        </div>

        <div class="sidebar-body" id="panel-nav">
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
                    <button class="btn btn-small btn-icon" type="submit" title="{{ __('Sign out') }}" aria-label="{{ __('Sign out') }}"><x-wake::icon name="logout" /></button>
                </form>
            </div>
        </div>
    </aside>

    <main class="main" id="main">
        <div class="main-inner">
            @if (config('wake.closed'))
                <div class="banner" role="status"><span class="kv"><span>{{ __('Site') }}</span><span>{{ __('closed') }}</span></span>
                    <span>{{ __('The site is closed: only signed-in team members can see it.') }}</span>
                    @if ($account->can('settings.manage'))<a href="{{ route('wake.settings') }}">{{ __('Open it') }} →</a>@endif</div>
            @endif
            @if (session('status') && ! str_contains((string) $slot, e(session('status'))))
                <div class="flash" role="status">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</x-wake::layout.base>
