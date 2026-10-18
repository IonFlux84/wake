<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">Wake {{ \Wake\Wake::VERSION }}</div>
            <h1 class="title">{{ __('Settings') }}</h1>
        </div>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    @foreach ($problems as $problem)<div class="flash error" role="alert">{{ $problem }}</div>@endforeach

    @if ($isAdmin)
        <form wire:submit="saveSite" style="display:flex;flex-direction:column;gap:16px">
            <section class="card" style="flex-direction:row;align-items:center;gap:16px;{{ $closed ? 'background:var(--night);color:#fff;border-color:var(--night)' : '' }}">
                <x-wake::icon name="key" size="22" />
                <div style="flex:1">
                    <h2 class="h2" style="{{ $closed ? 'color:#fff' : '' }}">{{ __('Closed site') }}</h2>
                    <p style="margin:4px 0 0;font-size:14px;{{ $closed ? 'color:#bdbdbd' : '' }}" class="{{ $closed ? '' : 'muted' }}">{{ __('Only signed-in team members see the website. Everyone else lands on the sign-in page, and search engines are kept out. Good while building a site.') }}</p>
                </div>
                <label class="check" style="white-space:nowrap"><input type="checkbox" wire:model.live="closed"> {{ $closed ? __('Closed') : __('Open') }}</label>
            </section>
            <section class="grid grid-2">
                <div class="card form">
                    <h2 class="h2">{{ __('Website') }}</h2>
                    @foreach ($site as $code => $values)
                        @if ($languages->isMultilingual())<div class="label">{{ $languages->name($code) }}</div>@endif
                        <div class="field"><label for="title-{{ $code }}">{{ __('Title') }}</label><input id="title-{{ $code }}" class="input" wire:model="site.{{ $code }}.title">@error("site.$code.title")<span class="error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="desc-{{ $code }}">{{ __('Description') }}</label><textarea id="desc-{{ $code }}" class="textarea" style="min-height:70px" wire:model="site.{{ $code }}.description"></textarea></div>
                    @endforeach
                    <div class="field"><label for="tz">{{ __('Time zone') }}</label>
                        <select id="tz" class="select" wire:model="timezone">@foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach</select></div>
                </div>
                <div class="card form">
                    <h2 class="h2">{{ __('Addresses and languages') }}</h2>
                    <div class="field"><label for="post-url">{{ __('Address of posts') }}</label><input id="post-url" class="input mono" wire:model="postUrl">
                        <span class="hint">{{ __('Placeholders: {slug}, {year}, {month}, {day}. Example: {year}/{slug}') }}</span>@error('postUrl')<span class="error">{{ __('The address needs {slug}.') }}</span>@enderror</div>
                    @if ($languages->isMultilingual())
                        <div class="field"><label for="missing">{{ __('When a translation is missing') }}</label>
                            <select id="missing" class="select" wire:model="missingTranslation"><option value="hide">{{ __('Show nothing (404)') }}</option><option value="fallback">{{ __('Show the default language with a notice') }}</option></select></div>
                    @endif
                    <p class="muted" style="margin:0;font-size:13px">{{ __('Languages are set in site/config.php.') }}</p>
                    <h2 class="h2" style="margin-top:8px">{{ __('Panel') }}</h2>
                    <div class="field"><label for="panel-lang">{{ __('Language of the panel') }}</label>
                        <select id="panel-lang" class="select" wire:model="panelLanguage"><option value="de">Deutsch</option><option value="en">English</option></select>
                        <span class="hint">{{ __('For everyone who has not chosen their own language.') }}</span></div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        @foreach (['#C6FF3D', '#3D5AFE', '#FF2D87', '#FF6A1A'] as $color)
                            <button type="button" wire:click="$set('accent', '{{ $color }}')" aria-label="{{ $color }}" style="width:36px;height:36px;border-radius:10px;border:{{ strtoupper($accent) === $color ? '3px solid var(--ink)' : '1px solid var(--line)' }};background:{{ $color }};cursor:pointer"></button>
                        @endforeach
                        <input class="input mono" style="width:130px" wire:model.live.debounce.500ms="accent" aria-label="{{ __('Own colour') }}">
                    </div>
                </div>
            </section>
            <section class="grid grid-2">
                <div class="card form">
                    <h2 class="h2">{{ __('Statistics') }}</h2>
                    <label class="check"><input type="checkbox" wire:model="trackingEnabled"> {{ __('Count visits') }}</label>
                    <div class="field"><label for="level1">{{ __('Counting without cookies') }}</label>
                        <select id="level1" class="select" wire:model="level1"><option value="always">{{ __('For every visitor') }}</option><option value="consent">{{ __('Only after consent in the banner') }}</option></select></div>
                    <label class="check"><input type="checkbox" wire:model="privacySignals"> {{ __('Ignore visitors with Do Not Track or Global Privacy Control') }}</label>
                    <div class="field"><label for="retention">{{ __('Keep raw data (days)') }}</label><input id="retention" type="number" min="1" class="input" wire:model="retention"></div>
                </div>
                <div class="card form">
                    <h2 class="h2">{{ __('Assistants') }}</h2>
                    <label class="check"><input type="checkbox" wire:model="approval"> {{ __('Assistants may only ask for publishing; a person approves') }}</label>
                    @if ($warnings)
                        <h2 class="h2" style="margin-top:8px">{{ __('Notes on your content') }}</h2>
                        @foreach ($warnings as $warning)<p style="margin:0;font-size:13px" class="mono">{{ $warning }}</p>@endforeach
                    @endif
                </div>
            </section>
            <div class="actions"><button class="btn btn-primary" type="submit">{{ __('Save settings') }}</button><span class="muted" style="font-size:13px">{{ __('Stored in site/settings.yml.') }}</span></div>
        </form>
    @endif

    @if ($isAdmin)
        <livewire:wake.mail-settings-form />
    @endif

    @if ($isAdmin)
        <div class="card">
            <div class="card-head"><h2 class="h2">{{ __('Updates') }}</h2><span class="badge">{{ \Wake\Wake::VERSION }}</span></div>
            <p class="muted" style="margin:0;font-size:14px">{{ __('Wake only asks GitHub for a new version when you click the button.') }}</p>
            @if ($release)
                <div class="night" style="padding:18px">
                    <h3 class="h2" style="margin:0 0 8px">{{ __('Wake :version is available', ['version' => $release['version']]) }}</h3>
                    <div class="muted" style="white-space:pre-line;font-size:13px;max-height:180px;overflow:auto">{{ \Illuminate\Support\Str::limit($release['notes'], 1500) }}</div>
                </div>
                @if (auth()->user()->role()->isOwner())
                    <div class="actions"><button type="button" class="btn btn-primary" wire:click="installUpdate" wire:confirm="{{ __('Install the update now? Your content and settings are not touched; the current version is kept as a backup.') }}" wire:loading.attr="disabled">{{ __('Install') }}</button><span wire:loading wire:target="installUpdate" class="muted">{{ __('Installing …') }}</span></div>
                @else
                    <p class="muted" style="margin:0">{{ __('Only the owner can install updates.') }}</p>
                @endif
            @endif
            @if ($updateMessage)<p style="margin:0">{{ $updateMessage }}</p>@endif
            <div class="actions"><button type="button" class="btn" wire:click="checkForUpdates" wire:loading.attr="disabled">{{ __('Check for updates') }}</button></div>
        </div>

        <form class="card form" wire:submit="importGhost">
            <h2 class="h2">{{ __('Import from Ghost') }}</h2>
            <p class="muted" style="margin:0;font-size:14px">{{ __('In Ghost, open Settings → Labs → Export your content. Upload the JSON file here. With the address of the old site, Wake also copies the images.') }}</p>
            <div class="grid grid-2" style="gap:12px">
                <div class="field"><label for="ghost-file">{{ __('Export file (JSON)') }}</label><input id="ghost-file" type="file" accept=".json,application/json" class="input" wire:model="ghostFile">@error('ghostFile')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="ghost-url">{{ __('Address of the old site (optional)') }}</label><input id="ghost-url" type="url" class="input" wire:model="ghostUrl" placeholder="https://blog.example.org">@error('ghostUrl')<span class="error">{{ $message }}</span>@enderror</div>
            </div>
            <div class="actions"><button class="btn btn-dark" type="submit" wire:loading.attr="disabled"><x-wake::icon name="upload" />{{ __('Import') }}</button><span wire:loading wire:target="importGhost" class="muted">{{ __('Importing …') }}</span></div>
            @if ($importResult)
                <div class="flash" role="status">{{ __('Imported :posts posts, :pages pages, :tags tags and :images images. Skipped :skipped.', $importResult['counts']) }}</div>
                @foreach ($importResult['warnings'] as $warning)<p class="muted mono" style="margin:0;font-size:12px">{{ $warning }}</p>@endforeach
            @endif
        </form>
    @endif

    <section class="grid grid-2">
        <form class="card form" wire:submit="saveAccount">
            <h2 class="h2">{{ __('Your account') }}</h2>
            <div class="field"><label for="me-name">{{ __('Name') }}</label><input id="me-name" class="input" wire:model="name">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="me-email">{{ __('Email address') }}</label><input id="me-email" type="email" class="input" wire:model="email">@error('email')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="me-lang">{{ __('Language of the panel') }}</label><select id="me-lang" class="select" wire:model="language"><option value="">{{ __('Like the site (:language)', ['language' => config('wake.panel.language') === 'en' ? 'English' : 'Deutsch']) }}</option><option value="de">Deutsch</option><option value="en">English</option></select></div>
            <div class="grid grid-2" style="gap:12px">
                <div class="field"><label for="pw-old">{{ __('Current password') }}</label><input id="pw-old" type="password" class="input" wire:model="currentPassword" autocomplete="current-password">@error('currentPassword')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="pw-new">{{ __('New password') }}</label><input id="pw-new" type="password" class="input" wire:model="newPassword" autocomplete="new-password">@error('newPassword')<span class="error">{{ $message }}</span>@enderror</div>
            </div>
            <div class="actions"><button class="btn btn-primary" type="submit">{{ __('Save account') }}</button></div>
        </form>
        <div class="card">
            <h2 class="h2">{{ __('Latest changes') }}</h2>
            @forelse ($history as $item)
                <div style="display:flex;justify-content:space-between;gap:12px;font-size:13px">
                    <span style="min-width:0"><b>{{ __('wake::history.'.$item['action']) }}</b> <span class="mono muted">{{ $item['id'] }}@if (! empty($item['language'])) ({{ $item['language'] }})@endif</span>
                        @if (($item['via'] ?? '') === 'mcp')<span class="badge mcp">MCP · {{ $item['token'] ?? '' }}</span>@endif</span>
                    <span class="mono muted" style="white-space:nowrap">{{ $item['account'] ?? '' }} · {{ \Carbon\CarbonImmutable::parse($item['time'])->locale(app()->getLocale())->diffForHumans() }}</span>
                </div>
            @empty
                <p class="muted" style="margin:0">{{ __('Nothing changed yet.') }}</p>
            @endforelse
        </div>
    </section>

    <section class="card" style="max-width:640px">
        <livewire:wake.two-factor-settings />
    </section>
</div>
