<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count redirect|:count redirects', count($rows)) }}</div>
            <h1 class="title">{{ __('Redirects') }}</h1>
            <p class="lead">{{ __('Old addresses lead to the right place. Wake adds one by itself when the address of a published entry changes.') }}</p>
        </div>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    <form class="card form" wire:submit="add">
        <h2 class="h2">{{ __('New redirect') }}</h2>
        <div class="grid grid-3" style="gap:12px;align-items:start">
            <div class="field"><label for="r-from">{{ __('Old address') }}</label><input id="r-from" class="input" wire:model="from" placeholder="/alte-seite">@error('from')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="r-to">{{ __('Leads to') }}</label>
                <input id="r-to" class="input" wire:model="to" list="r-entries" placeholder="{{ __('Entry or https://…') }}">
                <datalist id="r-entries">@foreach ($entries as $entry)<option value="{{ $entry->id() }}">{{ $entry->title() }}</option>@endforeach</datalist>
                @error('to')<span class="error">{{ $message }}</span>@enderror</div>
            @if ($languages->isMultilingual())
                <div class="field"><label for="r-lang">{{ __('Language') }}</label><select id="r-lang" class="select" wire:model.live="language">@foreach ($languages->all() as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select></div>
            @endif
        </div>
        <div class="actions"><button class="btn btn-primary" type="submit">{{ __('Save redirect') }}</button></div>
    </form>

    <section class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('Old address') }}</th><th>{{ __('Leads to') }}</th>@if ($languages->isMultilingual())<th>{{ __('Language') }}</th>@endif<th></th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="r-{{ $row['language'] }}-{{ $row['path'] }}">
                        <td class="mono">/{{ $languages->prefix($row['language']) ? $languages->prefix($row['language']).'/' : '' }}{{ $row['path'] }}</td>
                        <td>@if ($row['entry'])<b>{{ $row['entry']->title() }}</b><br><span class="muted mono" style="font-size:12px">{{ parse_url($row['entry']->url(), PHP_URL_PATH) }}</span>@else<a href="{{ $row['target'] }}" target="_blank" rel="noopener">{{ $row['target'] }}</a>@endif
                            @if (! $row['entry'] && ! str_starts_with($row['target'], 'http'))<span class="badge">{{ __('target missing') }}</span>@endif</td>
                        @if ($languages->isMultilingual())<td><span class="chip current">{{ $row['language'] }}</span></td>@endif
                        <td style="text-align:right"><button type="button" class="btn btn-small btn-danger" wire:click="remove('{{ $row['language'] }}', @js($row['path']))" wire:confirm="{{ __('Remove this redirect? The old address then leads nowhere.') }}" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><x-wake::icon name="trash" /></button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ __('No redirects yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
