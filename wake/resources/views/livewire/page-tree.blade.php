<section class="card" style="gap:0;padding:8px 0"
    x-data="{ drag: null, over: null, zone: null,
        zoneOf(e) { const r = e.currentTarget.getBoundingClientRect(), y = (e.clientY - r.top) / r.height; return y < .3 ? 'before' : (y > .7 ? 'after' : 'inside') } }">
    @if (session('status'))<div class="flash" role="status" style="margin:8px 16px">{{ session('status') }}</div>@endif
    @if ($canArrange)
        <p class="muted" style="font-size:13px;margin:4px 20px 10px">{{ __('Drag pages to change the order, or drop them onto another page to put them inside it. Pages in the menu order appear in the main menu.') }}</p>
    @endif
    <ul class="tree" role="tree" aria-label="{{ __('Page tree') }}">
        @forelse ($tree as $node)
            @php($page = $node['page'])
            <li role="treeitem" aria-level="{{ $node['depth'] + 1 }}" wire:key="tree-{{ $page->id() }}"
                class="tree-row" style="--depth:{{ $node['depth'] }}"
                @if ($canArrange && ! $node['home'])
                    draggable="true"
                    @dragstart="drag = @js($page->id()); $event.dataTransfer.effectAllowed = 'move'"
                    @dragend="drag = null; over = null"
                @endif
                @if ($canArrange)
                    @dragover.prevent="if (drag && drag !== @js($page->id())) { over = @js($page->id()); zone = zoneOf($event) }"
                    @dragleave="if (over === @js($page->id())) over = null"
                    @drop.prevent="if (drag && drag !== @js($page->id())) $wire.move(drag, @js($page->id()), zone); over = null"
                    :class="over === @js($page->id()) && 'drop-' + zone"
                @endif>
                @if ($canArrange)<span class="tree-grip" aria-hidden="true">@unless ($node['home'])<x-wake::icon name="grip" />@endunless</span>@endif
                <span class="tree-title">
                    <a href="{{ route('wake.entries.edit', ['type' => 'page', 'id' => $page->id()]) }}">{{ $page->title() }}</a>
                    @if ($node['home'])<span class="badge">{{ __('Home page') }}</span>@elseif (! $node['listed'])<span class="badge muted">{{ __('not in the menu') }}</span>@endif
                    <span class="muted mono" style="font-size:12px">/{{ $page->path() }}</span>
                </span>
                <span class="status {{ $page->isScheduled() ? 'scheduled' : ($page->isPublished() ? 'published' : 'draft') }}" title="{{ $page->isScheduled() ? __('Scheduled') : ($page->isPublished() ? __('Published') : __('Draft')) }}"></span>
                @if ($canArrange && ! $node['home'])
                    <span class="tree-actions">
                        <button type="button" class="btn btn-icon" wire:click="step(@js($page->id()), 'up')" @disabled(! $node['listed'] || $node['first']) title="{{ __('Move up') }}" aria-label="{{ __('Move up') }}">↑</button>
                        <button type="button" class="btn btn-icon" wire:click="step(@js($page->id()), 'down')" @disabled(! $node['listed'] || $node['last']) title="{{ __('Move down') }}" aria-label="{{ __('Move down') }}">↓</button>
                        <button type="button" class="btn btn-icon" wire:click="step(@js($page->id()), 'out')" @disabled($node['depth'] === 0) title="{{ __('Move out of the parent page') }}" aria-label="{{ __('Move out of the parent page') }}">←</button>
                        <button type="button" class="btn btn-icon" wire:click="step(@js($page->id()), 'in')" @disabled(! $node['listed'] || $node['first']) title="{{ __('Move into the page above') }}" aria-label="{{ __('Move into the page above') }}">→</button>
                        <button type="button" class="btn btn-small" wire:click="toggleListed(@js($page->id()))">{{ $node['listed'] ? __('Remove from menu order') : __('Add to menu order') }}</button>
                    </span>
                @endif
            </li>
        @empty
            <li class="muted" style="padding:12px 20px">{{ __('Nothing here yet.') }}</li>
        @endforelse
    </ul>
</section>
