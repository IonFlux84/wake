<div style="display:flex;flex-direction:column;gap:20px">
    <header class="page-head">
        <div>
            <div class="eyebrow">{{ trans_choice(':count file|:count files', $total) }}</div>
            <h1 class="title">{{ __('Media') }}</h1>
            <p class="lead">{{ __('All images and documents of the site, in content/media/. Click a file for its details and where it is used.') }}</p>
        </div>
        <div class="segmented" role="group" aria-label="{{ __('Kind of files') }}">
            <button type="button" wire:click="$set('kind', 'images')" @class(['on' => $kind === 'images']) aria-pressed="{{ $kind === 'images' ? 'true' : 'false' }}">{{ __('Images') }}</button>
            <button type="button" wire:click="$set('kind', 'files')" @class(['on' => $kind === 'files']) aria-pressed="{{ $kind === 'files' ? 'true' : 'false' }}">{{ __('Other files') }}</button>
        </div>
    </header>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif

    @if ($selected)
        <section class="card" style="gap:16px" wire:key="details-{{ $file }}">
            <div class="card-head"><h2 class="h2"><span class="mono">{{ $selected->reference() }}</span></h2><button type="button" class="btn btn-small" wire:click="$set('file', '')">{{ __('Close') }}</button></div>
            <div class="grid grid-2" style="gap:20px;align-items:start">
                <div x-data="{ set(e) { const r = e.currentTarget.getBoundingClientRect(); $wire.setFocus(Math.round((e.clientX - r.left) / r.width * 100), Math.round((e.clientY - r.top) / r.height * 100)) } }">
                    @if ($selected->isImage())
                        <div style="position:relative;cursor:crosshair;border-radius:6px;overflow:hidden" @click="set($event)" title="{{ __('Click on the most important spot') }}">
                            <img src="{{ $selected->url(960) }}" alt="" style="width:100%;display:block">
                            @php([$fx, $fy] = explode(' ', $focus))
                            <span style="position:absolute;left:{{ $fx }};top:{{ $fy }};width:22px;height:22px;margin:-11px 0 0 -11px;border-radius:50%;background:#fff;border:2px solid #000;outline:1px solid #fff;pointer-events:none"></span>
                        </div>
                        <p class="muted" style="font-size:13px;margin:8px 0 0">{{ __('Focus point: click on the most important spot. Cards crop the image around it.') }} <span class="mono">{{ $focus }}</span></p>
                        <div class="grid grid-3" style="gap:8px;margin-top:10px">
                            @foreach (['16/9', '1/1', '3/4'] as $ratio)
                                <img src="{{ $selected->url(640) }}" alt="" style="width:100%;aspect-ratio:{{ $ratio }};object-fit:cover;object-position:{{ $focus }};border-radius:4px">
                            @endforeach
                        </div>
                    @endif
                    <p class="muted" style="font-size:12px;margin:10px 0 0">{{ number_format($selected->filesize() / 1024, 0, ',', '.') }} KB @if ($selected->width()) · {{ $selected->width() }} × {{ $selected->height() }} px @endif </p>
                    <div class="label" style="margin-top:16px">{{ __('Used in') }}</div>
                    @forelse ($usedBy as $entry)
                        <a href="{{ route('wake.entries.edit', ['type' => $entry->type(), 'id' => $entry->id()]) }}" style="display:block;font-size:13px">{{ $entry->title() }} <span class="muted mono">{{ $entry->id() }}</span></a>
                    @empty
                        <p class="muted" style="font-size:13px;margin:4px 0 0">{{ __('Not used in any text.') }}</p>
                    @endforelse
                </div>
                <form class="form" wire:submit="saveDetails">
                    <fieldset @disabled(! $mayEdit) style="border:0;padding:0;margin:0;display:contents">
                    @foreach ($languages->all() as $code => $langName)
                        @if ($languages->isMultilingual())<div class="label">{{ $langName }}</div>@endif
                        <div class="field"><label for="alt-{{ $code }}">{{ __('Alt text') }}</label><input id="alt-{{ $code }}" class="input" wire:model="alt.{{ $code }}" placeholder="{{ __('What can be seen? For people who cannot see the image.') }}"></div>
                        <div class="field"><label for="cap-{{ $code }}">{{ __('Caption') }}</label><input id="cap-{{ $code }}" class="input" wire:model="caption.{{ $code }}"></div>
                    @endforeach
                    <div class="field"><label for="credit">{{ __('Credit') }}</label><input id="credit" class="input" wire:model="credit" placeholder="{{ __('e.g. Photo: Jane Doe') }}"></div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">{{ __('Save details') }}</button>
                        <a class="btn" href="{{ $selected->url() }}" target="_blank" rel="noopener">{{ __('Open file') }}</a>
                        @if ($mayDelete)
                            <button type="button" class="btn btn-danger" style="margin-left:auto" wire:click="deleteFile" wire:confirm="{{ $usedBy->isEmpty() ? __('Move this file to the trash?') : trans_choice('This file is used in :count text. Move it to the trash anyway? The text will lose it.|This file is used in :count texts. Move it to the trash anyway? The texts will lose it.', $usedBy->count()) }}" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}"><x-wake::icon name="trash" /></button>
                        @endif
                    </div>
                    </fieldset>
                </form>
            </div>
        </section>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <input type="search" class="input" style="max-width:300px" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by file name') }}" aria-label="{{ __('Search by file name') }}">
        <select class="input" style="max-width:240px" wire:model.live="folder" aria-label="{{ __('Folder') }}">
            <option value="">{{ __('All folders') }}</option>
            @foreach ($folders as $dir)<option value="{{ $dir }}">{{ $dir }}/</option>@endforeach
        </select>
        <label style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" wire:model.live="unused"> {{ __('Only unused') }}</label>
        <form wire:submit="uploadFile" style="display:flex;gap:8px;align-items:center;margin-left:auto">
            <input class="input mono" style="width:160px" wire:model="uploadFolder" list="media-folders" aria-label="{{ __('Upload to folder') }}" title="{{ __('Upload to folder') }}">
            <datalist id="media-folders">@foreach ($folders as $dir)<option value="{{ $dir }}">@endforeach</datalist>
            <input type="file" wire:model="upload" aria-label="{{ __('File') }}" style="font-size:13px;max-width:200px">
            <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="upload">{{ __('Upload') }}</button>
        </form>
    </div>
    @error('upload')<p class="error" role="alert">{{ $message }}</p>@enderror
    @error('uploadFolder')<p class="error" role="alert">{{ $message }}</p>@enderror
    <section class="grid grid-4">
        @forelse ($files as $item)
            <button type="button" class="card" style="padding:0;overflow:hidden;gap:0;text-align:left;cursor:pointer;font:inherit;{{ $file === $item['file']->relativePath() ? 'border-color:var(--text);outline:1px solid var(--text)' : '' }}" wire:click="select(@js($item['file']->relativePath()))">
                @if ($item['file']->isImage())
                    <img src="{{ $item['file']->url(640) }}" alt="" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;object-position:{{ $item['file']->focus() }}">
                @else
                    <span style="display:flex;align-items:center;justify-content:center;aspect-ratio:4/3;background:var(--hover);font:600 14px var(--mono)">.{{ $item['file']->extension() }}</span>
                @endif
                <span style="padding:12px 14px;display:block">
                    <b style="display:block;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $item['file']->name() }}</b>
                    <span class="muted mono" style="font-size:11px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ dirname($item['file']->relativePath()) !== '.' ? dirname($item['file']->relativePath()).'/' : 'media/' }}</span>
                    <span class="muted" style="font-size:12px">{{ number_format($item['file']->filesize() / 1024, 0, ',', '.') }} KB · {{ $item['used'] ? trans_choice('used :count time|used :count times', $item['used']) : __('unused') }}</span>
                    @if ($item['file']->isImage() && $item['file']->alt() === '')<span class="badge" style="margin-top:6px">{{ __('no alt text') }}</span>@endif
                </span>
            </button>
        @empty
            <p class="muted">{{ __('No files yet.') }}</p>
        @endforelse
    </section>
</div>
