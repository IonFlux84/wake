@php
    $filters = [
        'missing' => __('Need a target'),
        'redirect' => __('Redirected'),
        'exists' => __('Exist here'),
        'gone' => __('Gone'),
        'ignored' => __('Ignored'),
        'all' => __('All'),
    ];
    $reasons = [
        'same file name' => __('same file name'),
        'similar file name' => __('similar file name'),
        'same slug' => __('same slug'),
        'same title' => __('same title'),
        'similar slug' => __('similar slug'),
        'similar title' => __('similar title'),
        'parent page' => __('parent page'),
    ];
    $sources = ['home' => __('home'), 'sitemap' => 'sitemap', 'link' => __('link'), 'wayback' => 'wayback', 'list' => __('list')];
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count address found|:count addresses found', $counts['all']) }}</div>
            <h1 class="title">{{ __('Old addresses') }}</h1>
            <p class="lead">{{ __('Wake looks through your old website and checks every address here. Whatever leads nowhere gets a target, so links from search engines and other websites keep working.') }}</p>
        </div>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    <form class="card form" wire:submit="start">
        <h2 class="h2">{{ __('Search the old website') }}</h2>
        <div class="field"><label for="rs-site">{{ __('Address of the old website') }}</label>
            <input id="rs-site" class="input" type="url" wire:model="site" placeholder="https://www.example.org" @disabled($running)>
            @error('site')<span class="error">{{ $message }}</span>@enderror</div>
        <div class="field">
            <span class="label">{{ __('Where Wake looks') }}</span>
            <label class="check"><input type="checkbox" wire:model="sitemap" @disabled($running)> {{ __('Sitemap and robots.txt') }}</label>
            <label class="check"><input type="checkbox" wire:model="crawl" @disabled($running)> {{ __('Follow the links on every page') }}</label>
            <label class="check"><input type="checkbox" wire:model="wayback" @disabled($running)> {{ __('Wayback Machine: also addresses that are no longer linked anywhere') }}</label>
        </div>
        <details>
            <summary>{{ __('Add addresses by hand') }}</summary>
            <div class="field" style="margin-top:10px"><label for="rs-list">{{ __('One address per line, e.g. from Search Console or the server logs') }}</label>
                <textarea id="rs-list" class="textarea" rows="5" wire:model="list" placeholder="/old/page.html" @disabled($running)></textarea></div>
        </details>
        <div class="actions">
            <button class="btn btn-primary" type="submit" @disabled($running)>{{ $counts['all'] ? __('Search again') : __('Start search') }}</button>
            @if ($counts['all'] && ! $running)
                <button class="btn" type="button" wire:click="recheck">{{ __('Check all addresses again') }}</button>
                <button class="btn btn-danger" type="button" wire:click="clear" wire:confirm="{{ __('Forget the search and all addresses? Saved redirects stay.') }}">{{ __('Forget the search') }}</button>
            @endif
        </div>
    </form>

    @if ($running)
        <section class="card" wire:poll.750ms="work" aria-live="polite">
            <div class="card-head"><h2 class="h2">{{ match ($phase) { 'discover' => __('Reading sitemaps and archives …'), 'fetch' => __('Visiting the old website …'), default => __('Checking every address here …') } }}</h2>
                <button type="button" class="btn btn-small" wire:click="stop">{{ __('Stop') }}</button></div>
            <div class="bar" style="margin:12px 0 8px"><i style="width:{{ $progress }}%;animation:none"></i></div>
            <p class="muted" style="font-size:13px">{{ trans_choice(':count address so far|:count addresses so far', $counts['all']) }} · {{ __(':count visited', ['count' => $store->get('fetched')]) }}</p>
        </section>
    @endif

    @if ($store->get('messages'))
        <div class="note">@foreach ($store->get('messages') as $message)<div class="mono" style="font-size:12px">{{ $message }}</div>@endforeach</div>
    @endif

    @if ($counts['all'])
        <section class="kpis" aria-label="{{ __('Filter by state') }}" style="grid-template-columns:repeat(6,minmax(0,1fr))">
            @foreach ($filters as $key => $label)
                <button type="button" class="kpi" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                    <span class="kpi-label">{{ $label }}</span>
                    <span class="kpi-value">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </section>

        <div class="actions" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
            <input class="input" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search addresses and titles, e.g. shop/') }}" aria-label="{{ __('Search addresses and titles, e.g. shop/') }}" style="max-width:340px">
            <div class="actions" style="gap:6px">
                @if ($filter === 'missing' && $search !== '' && $total && ! $running)
                    <span class="muted" style="font-size:13px">{{ trans_choice('All :count shown:|All :count shown:', $total) }}</span>
                    <button type="button" class="btn btn-small" wire:click="bulk('gone')" wire:confirm="{{ trans_choice('Answer 410 for :count address?|Answer 410 for :count addresses?', $total) }}">410</button>
                    <button type="button" class="btn btn-small" wire:click="bulk('ignore')">{{ __('Ignore') }}</button>
                @endif
                @if ($safe && ! $running)
                    <button type="button" class="btn btn-primary" wire:click="acceptSafe" wire:confirm="{{ __('Take all suggestions from :score % on?', ['score' => \RedirectScout\Livewire\Scout::SAFE]) }}">{{ trans_choice('Take :count safe suggestion|Take :count safe suggestions', $safe) }}</button>
                @endif
            </div>
        </div>

        <datalist id="rs-targets">
            @foreach ($entries as $entry)<option value="{{ $entry->id() }}">{{ $entry->title() }}</option>@endforeach
            @foreach ($categories as $category)<option value="{{ parse_url($category->url(), PHP_URL_PATH) }}">{{ $category->title() }}</option>@endforeach
            <option value="gone">{{ __('Gone (410)') }}</option>
        </datalist>

        <section class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('Old address') }}</th><th>{{ __('Here') }}</th><th style="min-width:340px">{{ __('Target') }}</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $path => $row)
                        @php($key = md5((string) $path))
                        @php($state = \RedirectScout\Store::filterOf($row))
                        <tr wire:key="rs-{{ $key }}">
                            <td style="max-width:420px">
                                <a class="mono" style="word-break:break-all" href="{{ $row['url'] ?? '#' }}" target="_blank" rel="noopener">/{{ $path }}</a>
                                @if (! empty($row['title']))<br><span class="muted" style="font-size:13px">{{ $row['title'] }}</span>@endif
                                <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px">
                                    @foreach ($row['sources'] ?? [] as $source)<span class="chip">{{ $sources[$source] ?? $source }}</span>@endforeach
                                    @if (isset($row['old_status']) && $row['old_status'] !== 200)<span class="chip missing" title="{{ __('What the old website answers today') }}">{{ $row['old_status'] ?: __('no answer') }}</span>@endif
                                    @if (($row['note'] ?? null) === 'robots.txt')<span class="chip missing">robots.txt</span>@endif
                                </div>
                            </td>
                            <td>
                                @switch($state)
                                    @case('exists')<span class="status published">{{ __('exists') }}</span>@break
                                    @case('redirect')<span class="status scheduled">{{ __('redirected') }}</span>@break
                                    @case('gone')<span class="status draft">{{ __('gone') }}</span>@break
                                    @case('ignored')<span class="status draft">{{ __('ignored') }}</span>@break
                                    @case('unchecked')<span class="muted">…</span>@break
                                    @case('error')<span class="badge danger">{{ __('error') }}</span>@break
                                    @default<span class="badge danger">{{ __('leads nowhere') }}</span>
                                @endswitch
                            </td>
                            <td>
                                @if ($state === 'missing')
                                    @if ($row['suggestion'] ?? null)
                                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                                            <span style="font-size:13px">{{ __('Suggestion') }}: <b>{{ $row['suggestion']['label'] }}</b>
                                                <span class="muted">({{ $row['suggestion']['score'] }} %, {{ $reasons[$row['suggestion']['reason']] ?? $row['suggestion']['reason'] }})</span></span>
                                            <button type="button" class="btn btn-small" wire:click="accept('{{ $key }}')">{{ __('Take it') }}</button>
                                        </div>
                                    @endif
                                    <form style="display:flex;gap:6px;flex-wrap:wrap" wire:submit="save('{{ $key }}')">
                                        <input class="input" style="flex:1;min-width:180px" list="rs-targets" wire:model="targets.{{ $key }}" placeholder="{{ __('Entry, /path, media/…, https://…') }}" aria-label="{{ __('Target for :path', ['path' => '/'.$path]) }}">
                                        <button type="submit" class="btn btn-small btn-primary">{{ __('Save') }}</button>
                                        <button type="button" class="btn btn-small" wire:click="gone('{{ $key }}')" title="{{ __('Removed on purpose: answers 410') }}">410</button>
                                        <button type="button" class="btn btn-small" wire:click="ignore('{{ $key }}')">{{ __('Ignore') }}</button>
                                    </form>
                                    @error('targets.'.$key)<span class="error">{{ $message }}</span>@enderror
                                @elseif (in_array($state, ['redirect', 'gone'], true) && ($row['target'] ?? null))
                                    <span class="mono" style="font-size:13px;word-break:break-all">→ {{ $row['target'] }}</span>
                                    <button type="button" class="btn btn-small btn-danger" wire:click="remove('{{ $key }}')" wire:confirm="{{ __('Remove this redirect? The old address then leads nowhere.') }}" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><x-wake::icon name="trash" /></button>
                                @elseif ($state === 'ignored')
                                    <button type="button" class="btn btn-small" wire:click="ignore('{{ $key }}', false)">{{ __('Show again') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">{{ __('Nothing here.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($pages > 1)
                <div class="actions" style="justify-content:center;margin-top:12px">
                    <button type="button" class="btn btn-small" wire:click="$set('page', {{ $page - 1 }})" @disabled($page <= 1)>←</button>
                    <span class="muted mono" style="font-size:12px">{{ $page }} / {{ $pages }}</span>
                    <button type="button" class="btn btn-small" wire:click="$set('page', {{ $page + 1 }})" @disabled($page >= $pages)>→</button>
                </div>
            @endif
        </section>
    @endif
</div>
