<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count file|:count files', $files->count()) }}</div>
            <h1 class="title">{{ __('Media') }}</h1>
            <p class="lead">{{ __('Files live next to the text of their entry. Upload them in the editor; click a file here for its details.') }}</p>
        </div>
        <div class="segmented" role="group">
            <button type="button" wire:click="$set('kind', 'images')" @class(['on' => $kind === 'images'])>{{ __('Images') }}</button>
            <button type="button" wire:click="$set('kind', 'files')" @class(['on' => $kind === 'files'])>{{ __('Other files') }}</button>
        </div>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    @if ($selected)
        <section class="card" style="gap:16px" wire:key="details-{{ $file }}">
            <div class="card-head"><h2 class="h2">{{ $selected->name() }}</h2><button type="button" class="btn btn-small" wire:click="$set('file', '')">{{ __('Close') }}</button></div>
            <div class="grid grid-2" style="gap:20px;align-items:start">
                <div x-data="{ set(e) { const r = e.currentTarget.getBoundingClientRect(); $wire.setFocus(Math.round((e.clientX - r.left) / r.width * 100), Math.round((e.clientY - r.top) / r.height * 100)) } }">
                    @if ($selected->isImage())
                        <div style="position:relative;cursor:crosshair;border-radius:12px;overflow:hidden" @click="set($event)" title="{{ __('Click on the most important spot') }}">
                            <img src="{{ $selected->url(960) }}" alt="" style="width:100%;display:block">
                            @php([$fx, $fy] = explode(' ', $focus))
                            <span style="position:absolute;left:{{ $fx }};top:{{ $fy }};width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;border:3px solid #fff;box-shadow:0 0 0 2px #0a0a0a,0 0 14px var(--glow);background:var(--accent);pointer-events:none"></span>
                        </div>
                        <p class="muted" style="font-size:13px;margin:8px 0 0">{{ __('Focus point: click on the most important spot. Cards crop the image around it.') }} <span class="mono">{{ $focus }}</span></p>
                        <div class="grid grid-3" style="gap:8px;margin-top:10px">
                            @foreach (['16/9', '1/1', '3/4'] as $ratio)
                                <img src="{{ $selected->url(640) }}" alt="" style="width:100%;aspect-ratio:{{ $ratio }};object-fit:cover;object-position:{{ $focus }};border-radius:8px">
                            @endforeach
                        </div>
                    @endif
                    <p class="muted" style="font-size:12px;margin:10px 0 0">{{ number_format($selected->filesize() / 1024, 0, ',', '.') }} KB @if ($selected->width()) · {{ $selected->width() }} × {{ $selected->height() }} px @endif · {{ $selectedEntry?->title() }}</p>
                </div>
                <form class="form" wire:submit="saveDetails">
                    @foreach ($languages->all() as $code => $langName)
                        @if ($languages->isMultilingual())<div class="label">{{ $langName }}</div>@endif
                        <div class="field"><label for="alt-{{ $code }}">{{ __('Alt text') }}</label><input id="alt-{{ $code }}" class="input" wire:model="alt.{{ $code }}" placeholder="{{ __('What can be seen? For people who cannot see the image.') }}"></div>
                        <div class="field"><label for="cap-{{ $code }}">{{ __('Caption') }}</label><input id="cap-{{ $code }}" class="input" wire:model="caption.{{ $code }}"></div>
                    @endforeach
                    <div class="field"><label for="credit">{{ __('Credit') }}</label><input id="credit" class="input" wire:model="credit" placeholder="{{ __('e.g. Photo: Jane Doe') }}"></div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">{{ __('Save details') }}</button>
                        @if ($selectedEntry)<a class="btn" href="{{ route('wake.entries.edit', ['type' => $selectedEntry->type(), 'id' => $selectedEntry->id()]) }}">{{ __('Open entry') }}</a>@endif
                        <button type="button" class="btn btn-danger" style="margin-left:auto" wire:click="deleteFile" wire:confirm="{{ __('Move this file to the trash? Texts that show it will lose the image.') }}"><x-wake::icon name="trash" /></button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <input type="search" class="input" style="max-width:360px" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by file or entry') }}" aria-label="{{ __('Search by file or entry') }}">
    <section class="grid grid-4">
        @forelse ($files as $item)
            <button type="button" class="card" style="padding:0;overflow:hidden;gap:0;text-align:left;cursor:pointer;font:inherit;{{ $file === $item['entry']->id().'|'.$item['file']->name() ? 'box-shadow:0 0 0 2px var(--accent);border-color:var(--ink)' : '' }}" wire:click="select(@js($item['entry']->id()), @js($item['file']->name()))">
                @if ($item['file']->isImage())
                    <img src="{{ $item['file']->url(640) }}" alt="" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;object-position:{{ $item['file']->focus() }}">
                @else
                    <span style="display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;background:var(--hover);font:600 14px var(--mono)">.{{ $item['file']->extension() }}</span>
                @endif
                <span style="padding:12px 14px;display:block">
                    <b style="display:block;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $item['file']->name() }}</b>
                    <span class="muted" style="font-size:12px">{{ $item['entry']->title() }} · {{ number_format($item['file']->filesize() / 1024, 0, ',', '.') }} KB</span>
                    @if ($item['file']->isImage() && $item['file']->alt() === '')<span class="badge" style="margin-top:6px">{{ __('no alt text') }}</span>@endif
                </span>
            </button>
        @empty
            <p class="muted">{{ __('No files yet.') }}</p>
        @endforelse
    </section>
</div>
