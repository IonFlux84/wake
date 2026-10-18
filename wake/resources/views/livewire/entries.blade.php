@php
    $titles = ['post' => __('Posts'), 'page' => __('Pages'), 'tag' => __('Tags')];
    $statusLabels = ['all' => __('All'), 'published' => __('Published'), 'draft' => __('Drafts'), 'scheduled' => __('Scheduled')];
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count entry|:count entries', $counts['all']) }}</div>
            <h1 class="title">{{ $titles[$type] ?? $contentType->title }}</h1>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('wake.entries.create', ['type' => $type]) }}"><x-wake::icon name="plus" />{{ $type === 'page' ? __('New page') : ($type === 'post' ? __('New post') : __('New entry')) }}</a>
        </div>
    </header>

    <section class="grid grid-4" aria-label="{{ __('Filter by status') }}">
        @foreach ($statusLabels as $key => $label)
            <button type="button" class="card" wire:click="setStatus('{{ $key }}')" aria-pressed="{{ $status === $key ? 'true' : 'false' }}"
                style="text-align:left;cursor:pointer;font:inherit;{{ $status === $key ? 'background:var(--night);color:#fff;border-color:var(--night)' : '' }}">
                <span class="kpi-label" style="{{ $status === $key ? 'color:#bdbdbd' : '' }}">{{ $label }} @if ($key !== 'all')<span class="status {{ $key }}"></span>@endif</span>
                <span class="kpi-value" style="{{ $status === $key ? 'color:var(--accent);text-shadow:0 0 18px var(--glow)' : '' }}">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </section>

    <section class="card">
        <div class="card-head">
            <input type="search" class="input" style="max-width:360px" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by title') }}" aria-label="{{ __('Search by title') }}">
            @if ($categories)
                <select class="select" style="max-width:220px" wire:model.live="category" aria-label="{{ __('Category') }}">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $key => $cat)<option value="{{ $key }}">{{ $cat->title(app()->getLocale()) }}</option>@endforeach
                </select>
            @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Status') }}</th>@if ($languages->isMultilingual())<th>{{ __('Languages') }}</th>@endif<th>{{ __('Date') }}</th></tr></thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr class="link" wire:key="{{ $entry->id() }}">
                        <td>
                            <a href="{{ route('wake.entries.edit', ['type' => $type, 'id' => $entry->id()]) }}" style="text-decoration:none;font-weight:600">{{ $entry->title() }}</a>
                            @if ($entry->get('via') === 'mcp')<span class="badge mcp" title="{{ __('Created by an AI assistant') }}">MCP</span>@endif
                            @if ($entry->get('review'))<span class="badge" style="background:var(--accent);color:var(--on-accent)">{{ __('Waiting for approval') }}</span>@endif
                            <br><span class="muted mono" style="font-size:12px">/{{ $entry->path() }}</span>
                            @if ($entry->category())<span class="badge" style="margin-left:6px">{{ $entry->category()->title(app()->getLocale()) }}@if ($entry->get('start')) · {{ \Carbon\CarbonImmutable::parse($entry->get('start'))->locale(app()->getLocale())->isoFormat('D. MMM') }}@endif</span>@endif
                        </td>
                        <td><span class="status {{ $entry->isScheduled() ? 'scheduled' : ($entry->isPublished() ? 'published' : 'draft') }}">{{ $entry->isScheduled() ? __('Scheduled') : ($entry->isPublished() ? __('Published') : __('Draft')) }}</span></td>
                        @if ($languages->isMultilingual())
                            <td>
                                @foreach (\Wake\Livewire\Entries::translationStates($entry) as $code => $state)
                                    <span class="chip {{ $state }}" title="{{ ['current' => __('up to date'), 'outdated' => __('original changed since'), 'missing' => __('not translated')][$state] }}">{{ $code }}</span>
                                @endforeach
                            </td>
                        @endif
                        <td class="mono" style="font-size:13px;white-space:nowrap">{{ $entry->date()?->locale(app()->getLocale())->isoFormat('D. MMM YYYY') ?? '–' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ trim($search) !== '' ? __('Nothing matches your search.') : __('Nothing here yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())
            <div class="actions" style="justify-content:space-between">
                <button class="btn btn-small" wire:click="previousPage" @disabled($entries->onFirstPage())>← {{ __('Back') }}</button>
                <span class="muted mono" style="font-size:12px">{{ $entries->currentPage() }} / {{ $entries->lastPage() }}</span>
                <button class="btn btn-small" wire:click="nextPage" @disabled(! $entries->hasMorePages())>{{ __('Next') }} →</button>
            </div>
        @endif
    </section>
</div>
