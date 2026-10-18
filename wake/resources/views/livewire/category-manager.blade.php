@php
    $typeLabels = ['text' => __('Text'), 'textarea' => __('Long text'), 'number' => __('Number'), 'toggle' => __('Yes/No'), 'date' => __('Date'), 'datetime' => __('Date and time'), 'select' => __('Choice'), 'url' => __('Link'), 'email' => __('Email address')];
    foreach (app(\Wake\Plugins\Plugins::class)->fields as $pluginType => $pluginField) {
        $typeLabels[$pluginType] = is_array($pluginField['label']) ? ($pluginField['label'][app()->getLocale()] ?? reset($pluginField['label'])) : $pluginField['label'];
    }
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count category|:count categories', count($categories)) }}</div>
            <h1 class="title">{{ __('Categories') }}</h1>
            <p class="lead">{{ __('Each category brings its own fields in the editor and its own look on the website.') }}</p>
        </div>
        @if ($editing === null)<button type="button" class="btn btn-primary" wire:click="create"><x-wake::icon name="plus" />{{ __('New category') }}</button>@endif
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    @if ($editing === null)
        <section class="grid grid-3">
            @forelse ($categories as $key => $category)
                <div class="card">
                    <div class="card-head"><h2 class="h2">{{ $category->title(app()->getLocale()) }}</h2><x-wake::icon :name="$category->icon()" size="18" /></div>
                    <p class="muted" style="margin:0;font-size:14px">{{ $category->description(app()->getLocale()) ?: '—' }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                        @foreach ($languages->codes() as $code)<span class="badge mono">/{{ $languages->prefix($code) ? $languages->prefix($code).'/' : '' }}{{ $category->slug($code) }}</span>@endforeach
                    </div>
                    <p class="mono" style="margin:0;font-size:12px;color:var(--muted)">{{ trans_choice(':count field|:count fields', count($category->fields())) }} · {{ trans_choice(':count post|:count posts', $counts[$key]) }} · post-{{ $category->template() }}</p>
                    <div class="actions" style="margin-top:auto">
                        <button type="button" class="btn btn-small" wire:click="edit('{{ $key }}')">{{ __('Edit') }}</button>
                        <a class="btn btn-small" href="{{ $category->url($languages->default()) }}" target="_blank" rel="noopener">{{ __('View') }} ↗</a>
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('No categories yet.') }}</p>
            @endforelse
        </section>
    @else
        <form wire:submit="save" style="display:flex;flex-direction:column;gap:16px">
            <section class="grid grid-2">
                <div class="card form">
                    <h2 class="h2">{{ $editing === '' ? __('New category') : __('Edit category') }}</h2>
                    @foreach ($languages->all() as $code => $name)
                        @if ($languages->isMultilingual())<div class="label">{{ $name }}</div>@endif
                        <div class="grid grid-2" style="gap:12px">
                            <div class="field"><label for="t-{{ $code }}">{{ __('Name') }}</label><input id="t-{{ $code }}" class="input" wire:model="titles.{{ $code }}" placeholder="{{ __('e.g. Events') }}">@error('titles.'.$code)<span class="error">{{ $message }}</span>@enderror</div>
                            <div class="field"><label for="s-{{ $code }}">{{ __('Address') }}</label><input id="s-{{ $code }}" class="input mono" wire:model="slugs.{{ $code }}" placeholder="{{ __('e.g. events') }}">@error('slugs.'.$code)<span class="error">{{ __('Only small letters, numbers and dashes.') }}</span>@enderror</div>
                        </div>
                        <div class="field"><label for="d-{{ $code }}">{{ __('Description') }}</label><input id="d-{{ $code }}" class="input" wire:model="descriptions.{{ $code }}"></div>
                    @endforeach
                    @if ($editing === '')
                        <div class="field"><label for="key">{{ __('Key') }}</label><input id="key" class="input mono" wire:model="key" placeholder="{{ __('filled in from the name') }}">
                            <span class="hint">{{ __('Stored in posts as "category: key". Cannot be changed later.') }}</span>@error('key')<span class="error">{{ $message }}</span>@enderror</div>
                    @endif
                </div>
                <div class="card form">
                    <h2 class="h2">{{ __('On the website') }}</h2>
                    <div class="field"><label for="template">{{ __('Template') }}</label><input id="template" class="input mono" wire:model="template" placeholder="{{ $key ?: 'event' }}">
                        <span class="hint">{{ __('Posts use post-:name.blade.php of the theme, the category page category-:name.blade.php. Without them, the normal templates are used.', ['name' => $template ?: ($key ?: '…')]) }}</span></div>
                    <label class="check"><input type="checkbox" wire:model="ownUrl"> {{ __('Addresses below the category, e.g. /events/summer-fest') }}</label>
                    <div class="field"><label for="list">{{ __('Order on the category page') }}</label>
                        <select id="list" class="select" wire:model.live="list"><option value="newest">{{ __('Newest posts first') }}</option><option value="upcoming">{{ __('By date: upcoming first, past ones separately') }}</option></select></div>
                    @if ($list === 'upcoming')
                        <div class="field"><label for="datefield">{{ __('Date field') }}</label>
                            <select id="datefield" class="select" wire:model="dateField"><option value="">—</option>@foreach ($fields as $f)@if (in_array($f['type'], ['date', 'datetime'], true) && $f['key'] !== '')<option value="{{ $f['key'] }}">{{ $f['label_'.app()->getLocale()] ?: $f['key'] }}</option>@endif @endforeach</select></div>
                    @endif
                    <div class="field"><label for="icon">{{ __('Icon') }}</label>
                        <select id="icon" class="select" wire:model="icon">@foreach (['tag', 'calendar', 'posts', 'media', 'globe', 'team', 'stats', 'pages'] as $i)<option value="{{ $i }}">{{ $i }}</option>@endforeach</select></div>
                </div>
            </section>

            <section class="card">
                <div class="card-head"><h2 class="h2">{{ __('Fields in the editor') }}</h2><button type="button" class="btn btn-small" wire:click="addField"><x-wake::icon name="plus" />{{ __('Add field') }}</button></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>{{ __('Field name') }}</th><th>{{ __('Label') }} (DE)</th><th>{{ __('Label') }} (EN)</th><th>{{ __('Type') }}</th><th>{{ __('Required') }}</th><th>{{ __('Per language') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($fields as $i => $field)
                            <tr wire:key="field-{{ $i }}">
                                <td><input class="input mono" style="min-width:140px" wire:model="fields.{{ $i }}.key" placeholder="start">@error('fields.'.$i.'.key')<span class="error" style="display:block;font-size:12px">{{ $message }}</span>@enderror</td>
                                <td><input class="input" style="min-width:130px" wire:model="fields.{{ $i }}.label_de"></td>
                                <td><input class="input" style="min-width:130px" wire:model="fields.{{ $i }}.label_en"></td>
                                <td>
                                    <select class="select" wire:model.live="fields.{{ $i }}.type">@foreach ($types as $t)<option value="{{ $t }}">{{ $typeLabels[$t] }}</option>@endforeach</select>
                                    @if ($field['type'] === 'select')<textarea class="textarea" style="min-height:70px;margin-top:6px;font-size:13px" wire:model="fields.{{ $i }}.options" placeholder="{{ __('one per line: value = label') }}"></textarea>@endif
                                </td>
                                <td style="text-align:center"><input type="checkbox" wire:model="fields.{{ $i }}.required" aria-label="{{ __('Required') }}"></td>
                                <td style="text-align:center"><input type="checkbox" wire:model="fields.{{ $i }}.translate" aria-label="{{ __('Per language') }}"></td>
                                <td style="white-space:nowrap">
                                    <button type="button" class="btn btn-small" wire:click="moveField({{ $i }}, -1)" aria-label="{{ __('Up') }}">↑</button>
                                    <button type="button" class="btn btn-small" wire:click="moveField({{ $i }}, 1)" aria-label="{{ __('Down') }}">↓</button>
                                    <button type="button" class="btn btn-small btn-danger" wire:click="removeField({{ $i }})" aria-label="{{ __('Remove') }}"><x-wake::icon name="trash" /></button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="muted" style="margin:0;font-size:13px">{{ __('“Per language” off means one value for all languages, e.g. for dates or links.') }}</p>
            </section>

            <div class="actions">
                <button type="submit" class="btn btn-primary">{{ __('Save category') }}</button>
                <button type="button" class="btn" wire:click="cancel">{{ __('Cancel') }}</button>
                @if ($editing !== '')
                    <button type="button" class="btn btn-danger" style="margin-left:auto" wire:click="delete('{{ $editing }}')" wire:confirm="{{ __('Remove this category? Posts keep their texts and fields, but no longer have a category page.') }}"><x-wake::icon name="trash" />{{ __('Remove category') }}</button>
                @endif
            </div>
        </form>
    @endif
</div>
