@php($names = ['main' => __('Main menu'), 'footer' => __('Footer menu')])
<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ __('Site') }}</div>
            <h1 class="title">{{ __('Menus') }}</h1>
            <p class="lead">{{ __('Which pages and links your theme shows in the header and in the footer.') }}</p>
        </div>
    </header>

    <div class="segmented" role="tablist" style="align-self:flex-start">
        @foreach ($names as $key => $label)
            <button type="button" role="tab" @class(['on' => $menu === $key]) aria-selected="{{ $menu === $key ? 'true' : 'false' }}" wire:click="$set('menu', '{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    @if (! $custom && $menu === 'main')
        <section class="card">
            <div class="card-head"><h2 class="h2">{{ __('Follows the page tree') }}</h2>
                <button type="button" class="btn btn-primary" wire:click="customize">{{ __('Arrange by hand') }}</button>
            </div>
            <p class="muted" style="margin:0">{{ __('The main menu shows the pages in the menu order of the page tree, with their sub-pages. Change it under Pages, or arrange the menu by hand here.') }}</p>
            <ul class="tree">
                @forelse ($preview as $item)
                    <li class="tree-row" style="--depth:0">{{ $item->title() }}</li>
                    @foreach ($item->children() as $child)<li class="tree-row" style="--depth:1">{{ $child->title() }}</li>@endforeach
                @empty
                    <li class="tree-row muted">{{ __('No page is in the menu order yet.') }}</li>
                @endforelse
            </ul>
        </section>
    @else
        <section class="card" style="gap:0;padding:8px 0"
            x-data="{ drag: null, over: null, zone: null,
                zoneOf(e) { const r = e.currentTarget.getBoundingClientRect(), y = (e.clientY - r.top) / r.height; return y < .3 ? 'before' : (y > .7 ? 'after' : 'inside') } }">
            <ul class="tree" aria-label="{{ $names[$menu] }}">
                @php($rows = collect($items)->flatMap(fn ($item, $i) => [['item' => $item, 'path' => "$i", 'depth' => 0], ...collect($item['children'])->map(fn ($child, $j) => ['item' => $child, 'path' => "$i.children.$j", 'depth' => 1])]))
                @forelse ($rows as $row)
                    @php($item = $row['item'])
                    @php($page = $item['page'] ? $pages->get($item['page']) : null)
                    <li class="tree-row" style="--depth:{{ $row['depth'] }};align-items:flex-start" wire:key="menu-{{ $item['key'] }}"
                        draggable="true"
                        @dragstart="drag = @js($item['key']); $event.dataTransfer.effectAllowed = 'move'"
                        @dragend="drag = null; over = null"
                        @dragover.prevent="if (drag && drag !== @js($item['key'])) { over = @js($item['key']); zone = zoneOf($event) }"
                        @dragleave="if (over === @js($item['key'])) over = null"
                        @drop.prevent="if (drag && drag !== @js($item['key'])) $wire.move(drag, @js($item['key']), zone); over = null"
                        :class="over === @js($item['key']) && 'drop-' + zone">
                        <span class="tree-grip" style="margin-top:6px" aria-hidden="true"><x-wake::icon name="grip" /></span>
                        <details class="tree-title" style="display:block">
                            <summary style="cursor:pointer;list-style:none;display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
                                <strong>{{ collect($item['label'])->filter()->first() ?: ($page?->title() ?? ($item['page'] ? __('Missing page') : $item['url'])) }}</strong>
                                <span class="badge">{{ $item['page'] ? __('Page') : __('Link') }}</span>
                                <span class="muted mono" style="font-size:12px">{{ $page ? '/'.$page->path() : ($item['page'] ? $item['page'] : $item['url']) }}</span>
                                @if ($page && ! $page->isPublished())<span class="badge muted">{{ __('not published, hidden') }}</span>@endif
                            </summary>
                            <div class="form" style="margin-top:12px;max-width:520px">
                                @foreach ($languages->all() as $code => $langName)
                                    <div class="field"><label for="label-{{ $item['key'] }}-{{ $code }}">{{ __('Label') }}@if ($languages->isMultilingual()) · {{ $langName }}@endif</label>
                                        <input id="label-{{ $item['key'] }}-{{ $code }}" class="input" wire:model="items.{{ $row['path'] }}.label.{{ $code }}" placeholder="{{ $page?->title() ?? '' }}"></div>
                                @endforeach
                                @unless ($item['page'])
                                    <div class="field"><label for="url-{{ $item['key'] }}">{{ __('Address') }}</label><input id="url-{{ $item['key'] }}" class="input" wire:model="items.{{ $row['path'] }}.url"></div>
                                @endunless
                            </div>
                        </details>
                        <span class="tree-actions">
                            <button type="button" class="btn btn-icon" wire:click="step(@js($item['key']), 'up')" title="{{ __('Move up') }}" aria-label="{{ __('Move up') }}">↑</button>
                            <button type="button" class="btn btn-icon" wire:click="step(@js($item['key']), 'down')" title="{{ __('Move down') }}" aria-label="{{ __('Move down') }}">↓</button>
                            @if ($row['depth'] === 1)
                                <button type="button" class="btn btn-icon" wire:click="step(@js($item['key']), 'out')" title="{{ __('Move out of the parent page') }}" aria-label="{{ __('Move out of the parent page') }}">←</button>
                            @elseif ($item['children'] === [])
                                <button type="button" class="btn btn-icon" wire:click="step(@js($item['key']), 'in')" title="{{ __('Move into the item above') }}" aria-label="{{ __('Move into the item above') }}">→</button>
                            @endif
                            <button type="button" class="btn btn-icon btn-danger" wire:click="remove(@js($item['key']))" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-wake::icon name="trash" /></button>
                        </span>
                    </li>
                @empty
                    <li class="tree-row muted">{{ __('This menu is empty. Add pages or links below.') }}</li>
                @endforelse
            </ul>
        </section>
    @endif

    <section class="grid grid-2" style="gap:20px;align-items:start">
        <form class="card form" wire:submit="addPageItem">
            <h2 class="h2">{{ __('Add a page') }}</h2>
            <div class="field">
                <label for="add-page">{{ __('Page') }}</label>
                <select id="add-page" class="select" wire:model="addPage">
                    <option value="">{{ __('Choose a page') }}</option>
                    @foreach ($pages as $id => $page)<option value="{{ $id }}">{{ str_repeat('– ', substr_count($id, '/') - 1) }}{{ $page->title() }}</option>@endforeach
                </select>
            </div>
            <div class="actions"><button type="submit" class="btn"><x-wake::icon name="plus" />{{ __('Add') }}</button></div>
        </form>
        <form class="card form" wire:submit="addLink">
            <h2 class="h2">{{ __('Add a link') }}</h2>
            <div class="field"><label for="link-label">{{ __('Label') }}</label><input id="link-label" class="input" wire:model="linkLabel">@error('linkLabel')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="link-url">{{ __('Address') }}</label><input id="link-url" class="input" wire:model="linkUrl" placeholder="https://">@error('linkUrl')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="actions"><button type="submit" class="btn"><x-wake::icon name="plus" />{{ __('Add') }}</button></div>
        </form>
    </section>

    @if ($custom)
        <div class="actions">
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Save menu') }}</button>
            @if ($menu === 'main')
                <button type="button" class="btn" wire:click="automatic" wire:confirm="{{ __('Forget this menu and show the page tree again?') }}">{{ __('Follow the page tree again') }}</button>
            @endif
        </div>
    @endif
</div>
